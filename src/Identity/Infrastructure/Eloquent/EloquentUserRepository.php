<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Eloquent;

use DateTimeImmutable;
use Identity\Application\DocumentAlreadyInUse;
use Identity\Application\EmailAlreadyRegistered;
use Identity\Application\ProfileNotFound;
use Identity\Application\UserRepository;
use Identity\Domain\Document;
use Identity\Domain\DocumentType;
use Identity\Domain\EmailAddress;
use Identity\Domain\HashedPassword;
use Identity\Domain\NewUser;
use Identity\Domain\PersonName;
use Identity\Domain\PhoneNumber;
use Identity\Domain\PlatformRole;
use Identity\Domain\ProfileChanges;
use Identity\Domain\RegisteredUser;
use Identity\Domain\UserCredentials;
use Identity\Domain\UserIdentity;
use Identity\Domain\UserProfile;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

final readonly class EloquentUserRepository implements UserRepository
{
    /** Nome da constraint criada pela migration — ver o catch em add(). */
    private const EMAIL_UNIQUE_CONSTRAINT = 'users_email_unique';

    /** Nome da constraint de unicidade do documento — ver o catch em updateProfile(). */
    private const DOCUMENT_UNIQUE_CONSTRAINT = 'users_document_type_document_unique';

    public function __construct(
        private ConnectionResolverInterface $connections,
        private LoggerInterface $logger,
    ) {}

    public function existsByEmail(EmailAddress $email): bool
    {
        // A coluna é `citext`: a comparação já ignora a caixa, sem LOWER()
        // dos dois lados (que ainda por cima descartaria o índice).
        return $this->db()->table('users')
            ->where('email', $email->value())
            ->exists();
    }

    public function add(NewUser $user): RegisteredUser
    {
        $id = (string) Str::uuid7();
        $agora = new DateTimeImmutable;

        try {
            $this->db()->transaction(function () use ($user, $id, $agora): void {
                $this->db()->table('users')->insert([
                    'id' => $id,
                    'name' => $user->name->value(),
                    'email' => $user->email->value(),
                    'password_hash' => $user->password->value(),
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);

                $this->db()->table('user_platform_roles')->insert(
                    array_map(fn (PlatformRole $role): array => [
                        'user_id' => $id,
                        'role' => $role->value,
                        'granted_at' => $agora,
                    ], $user->roles)
                );
            });
        } catch (UniqueConstraintViolationException $e) {
            // Aqui é a corrida sendo fechada: entre o existsByEmail() do use
            // case e este INSERT cabe outro cadastro do mesmo e-mail. Quem
            // garante a unicidade é a constraint, e traduzi-la para a exceção
            // de negócio é responsabilidade deste adapter — o Application não
            // pode saber o que é uma violação de chave do Postgres.
            //
            // A checagem do nome da constraint evita mascarar um bug
            // diferente como "e-mail já cadastrado": a outra chave única
            // alcançável nesta transação é a primária de
            // user_platform_roles, e ela indicaria papel duplicado.
            if (! str_contains($e->getMessage(), self::EMAIL_UNIQUE_CONSTRAINT)) {
                throw $e;
            }

            throw EmailAlreadyRegistered::for($user->email);
        }

        return new RegisteredUser(
            id: $id,
            name: $user->name,
            email: $user->email,
            roles: $user->roles,
            registeredAt: $agora,
        );
    }

    public function findCredentialsByEmail(EmailAddress $email): ?UserCredentials
    {
        // Allowlist explícita (RULE 4). Este é o único método da aplicação
        // que traz `password_hash` — e ele existe só para a verificação do
        // login. Um `SELECT *` aqui levaria o hash para todo lugar que um dia
        // chamasse este repositório.
        $linha = $this->db()->table('users')
            ->select('id', 'name', 'email', 'password_hash')
            ->where('email', $email->value())
            ->first();

        if ($linha === null) {
            return null;
        }

        return new UserCredentials(
            $this->identidade($linha->id, $linha->name, $linha->email),
            HashedPassword::fromHash($linha->password_hash),
        );
    }

    public function findIdentityById(string $id): ?UserIdentity
    {
        // O id vem do `sub` de um token, ou seja, de fora. A coluna é `uuid`,
        // e o Postgres estoura com "invalid input syntax for type uuid" para
        // qualquer coisa que não seja um — o que transformaria um token
        // forjado em erro 500 em vez do 401 que ele merece.
        if (! self::pareceUuid($id)) {
            return null;
        }

        $linha = $this->db()->table('users')
            ->select('id', 'name', 'email')
            ->where('id', $id)
            ->first();

        return $linha === null ? null : $this->identidade($linha->id, $linha->name, $linha->email);
    }

    public function findProfileById(string $id): ?UserProfile
    {
        if (! self::pareceUuid($id)) {
            return null;
        }

        // Allowlist: sem `password_hash`. "Minha conta" não precisa dele, e o
        // que não é lido não vaza (RULE 4).
        $linha = $this->db()->table('users')
            ->select('id', 'name', 'email', 'phone', 'document', 'document_type')
            ->where('id', $id)
            ->first();

        if ($linha === null) {
            return null;
        }

        return new UserProfile(
            id: $linha->id,
            name: PersonName::fromString($linha->name),
            email: EmailAddress::fromString($linha->email),
            phone: $linha->phone === null ? null : PhoneNumber::fromString($linha->phone),
            document: $this->documentoDaLinha($linha->id, $linha->document, $linha->document_type),
        );
    }

    /**
     * Lê o documento gravado, tolerando um tipo que o enum não conhece mais.
     *
     * `DocumentType::from()` lançaria `ValueError`, e isso vira **500** — a
     * pessoa não conseguiria nem abrir o próprio perfil para corrigir. E não
     * é hipótese: `passport` foi um tipo válido e deixou de ser, e a coluna
     * não tem CHECK de propósito (tipo novo é valor novo no enum, sem
     * migration).
     *
     * Descartar falha **fechado** e é o mesmo tratamento que os papéis de
     * plataforma já recebem logo abaixo. O log existe porque um documento que
     * some da resposta sem explicação é pior do que o erro: ninguém
     * investiga o que não aparece.
     */
    private function documentoDaLinha(string $id, ?string $valor, ?string $tipo): ?Document
    {
        if ($valor === null || $tipo === null) {
            return null;
        }

        $tipoConhecido = DocumentType::tryFrom($tipo);

        if ($tipoConhecido === null) {
            $this->logger->warning('Documento com tipo desconhecido ignorado na leitura do perfil', [
                'user_id' => $id,
                'document_type' => $tipo,
            ]);

            return null;
        }

        return Document::fromString($tipoConhecido, $valor);
    }

    public function documentIsTakenByAnother(Document $document, string $ownerId): bool
    {
        return $this->db()->table('users')
            ->where('document_type', $document->type->value)
            ->where('document', $document->value)
            ->where('id', '!=', $ownerId)
            ->exists();
    }

    public function updateProfile(string $id, ProfileChanges $changes): UserProfile
    {
        // Só o que veio na requisição entra no UPDATE — o que o PATCH não
        // pediu para mudar não pode ser tocado.
        //
        // Montado com `if`, e não com `array_filter`, porque remover o
        // telefone é gravar `null` de propósito: um filtro que descarta nulos
        // não sabe distinguir "não informado" de "apague isto".
        $colunas = [];

        if ($changes->name !== null) {
            $colunas['name'] = $changes->name->value();
        }

        if ($changes->phone !== null) {
            $colunas['phone'] = $changes->phone->value();
        }

        if ($changes->removesPhone) {
            $colunas['phone'] = null;
        }

        if ($changes->document !== null) {
            $colunas['document'] = $changes->document->value;
            $colunas['document_type'] = $changes->document->type->value;
        }

        try {
            $this->db()->table('users')
                ->where('id', $id)
                ->update($colunas + ['updated_at' => new DateTimeImmutable]);
        } catch (UniqueConstraintViolationException $e) {
            // Mesma história do cadastro: entre a verificação do use case e o
            // UPDATE cabe outra conta gravando o mesmo documento. Quem garante
            // é a constraint; traduzi-la é deste adapter.
            if (! str_contains($e->getMessage(), self::DOCUMENT_UNIQUE_CONSTRAINT)) {
                throw $e;
            }

            throw DocumentAlreadyInUse::create();
        }

        return $this->findProfileById($id) ?? throw ProfileNotFound::create();
    }

    private static function pareceUuid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1;
    }

    private function identidade(string $id, string $nome, string $email): UserIdentity
    {
        return new UserIdentity(
            id: $id,
            name: PersonName::fromString($nome),
            email: EmailAddress::fromString($email),
            roles: $this->papeisDe($id),
        );
    }

    /** @return list<PlatformRole> */
    private function papeisDe(string $id): array
    {
        $papeis = $this->db()->table('user_platform_roles')
            ->select('role')
            ->where('user_id', $id)
            ->orderBy('role')
            ->pluck('role')
            ->all();

        // `tryFrom` e descarta o desconhecido: a coluna não tem CHECK (papel
        // novo é valor novo no enum, sem migration), então um valor estranho
        // vindo de um INSERT manual é possível. Descartar falha **fechado** —
        // o usuário perde um papel que a aplicação não sabe interpretar, em
        // vez de a requisição inteira estourar.
        return array_values(array_filter(array_map(
            static fn (string $papel): ?PlatformRole => PlatformRole::tryFrom($papel),
            $papeis,
        )));
    }

    private function db(): ConnectionInterface
    {
        return $this->connections->connection();
    }
}
