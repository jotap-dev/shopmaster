<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Eloquent;

use DateTimeImmutable;
use Identity\Application\EmailAlreadyRegistered;
use Identity\Application\UserRepository;
use Identity\Domain\EmailAddress;
use Identity\Domain\NewUser;
use Identity\Domain\PlatformRole;
use Identity\Domain\RegisteredUser;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

final readonly class EloquentUserRepository implements UserRepository
{
    /** Nome da constraint criada pela migration — ver o catch em add(). */
    private const EMAIL_UNIQUE_CONSTRAINT = 'users_email_unique';

    public function __construct(private ConnectionResolverInterface $connections) {}

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

    private function db(): ConnectionInterface
    {
        return $this->connections->connection();
    }
}
