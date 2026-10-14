<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use DateTimeImmutable;
use Identity\Application\EmailAlreadyRegistered;
use Identity\Application\PasswordHasher;
use Identity\Application\RegisterUser;
use Identity\Application\UserRepository;
use Identity\Domain\Document;
use Identity\Domain\EmailAddress;
use Identity\Domain\HashedPassword;
use Identity\Domain\InvalidEmailAddress;
use Identity\Domain\InvalidPassword;
use Identity\Domain\NewUser;
use Identity\Domain\PlainPassword;
use Identity\Domain\PlatformRole;
use Identity\Domain\ProfileChanges;
use Identity\Domain\RegisteredUser;
use Identity\Domain\UserCredentials;
use Identity\Domain\UserIdentity;
use Identity\Domain\UserProfile;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RegisterUserTest extends TestCase
{
    public function test_registra_o_usuario_e_devolve_ele_com_id(): void
    {
        $usuario = $this->useCase()->handle(
            name: 'Joao Pedro',
            email: 'joao@shopmaster.test',
            plainPassword: 'senha-forte-123',
        );

        $this->assertNotSame('', $usuario->id);
        $this->assertSame('Joao Pedro', $usuario->name->value());
        $this->assertSame('joao@shopmaster.test', $usuario->email->value());
    }

    public function test_o_usuario_nasce_com_o_papel_buyer(): void
    {
        $usuario = $this->useCase()->handle('Joao Pedro', 'joao@shopmaster.test', 'senha-forte-123');

        $this->assertTrue($usuario->hasRole(PlatformRole::Buyer));
        $this->assertSame([PlatformRole::Buyer], $usuario->roles);
    }

    public function test_normaliza_o_email_antes_de_gravar(): void
    {
        $repositorio = $this->repositorio();

        (new RegisterUser($repositorio, $this->hasher()))
            ->handle('Joao Pedro', '  Joao@ShopMaster.TEST ', 'senha-forte-123');

        $this->assertSame('joao@shopmaster.test', $repositorio->gravado?->email->value());
    }

    public function test_grava_o_hash_e_nunca_a_senha_em_texto_puro(): void
    {
        $repositorio = $this->repositorio();

        (new RegisterUser($repositorio, $this->hasher()))
            ->handle('Joao Pedro', 'joao@shopmaster.test', 'senha-forte-123');

        $gravado = $repositorio->gravado?->password->value();

        $this->assertSame('hash-de:senha-forte-123', $gravado);
        $this->assertNotSame('senha-forte-123', $gravado);
    }

    public function test_lanca_email_already_registered_quando_o_email_ja_existe(): void
    {
        $this->expectException(EmailAlreadyRegistered::class);

        $this->useCase(emailsExistentes: ['joao@shopmaster.test'])
            ->handle('Joao Pedro', 'joao@shopmaster.test', 'senha-forte-123');
    }

    public function test_reconhece_email_duplicado_mesmo_com_caixa_diferente(): void
    {
        $this->expectException(EmailAlreadyRegistered::class);

        $this->useCase(emailsExistentes: ['joao@shopmaster.test'])
            ->handle('Joao Pedro', 'JOAO@SHOPMASTER.TEST', 'senha-forte-123');
    }

    public function test_nao_gasta_hash_quando_o_email_ja_existe(): void
    {
        // bcrypt custa ~100ms de proposito. Hashear antes de descobrir que o
        // e-mail esta tomado e desperdicio numa rota publica, que e justamente
        // onde alguem pode martelar requisicoes.
        $hasher = $this->hasher();

        try {
            (new RegisterUser($this->repositorio(['joao@shopmaster.test']), $hasher))
                ->handle('Joao Pedro', 'joao@shopmaster.test', 'senha-forte-123');
        } catch (EmailAlreadyRegistered) {
            // esperado
        }

        $this->assertSame(0, $hasher->chamadas);
    }

    public function test_propaga_a_violacao_de_unicidade_vinda_do_banco(): void
    {
        // A verificacao previa nao e garantia: entre o SELECT e o INSERT cabe
        // outro cadastro do mesmo e-mail. Quem garante e a constraint UNIQUE,
        // e o use case tem de deixar essa excecao passar intacta.
        $this->expectException(EmailAlreadyRegistered::class);

        $repositorio = new class implements UserRepository
        {
            public function existsByEmail(EmailAddress $email): bool
            {
                return false;
            }

            public function add(NewUser $user): RegisteredUser
            {
                throw EmailAlreadyRegistered::for($user->email);
            }

            public function findCredentialsByEmail(EmailAddress $email): ?UserCredentials
            {
                throw new LogicException('not needed by this test');
            }

            public function findIdentityById(string $id): ?UserIdentity
            {
                throw new LogicException('not needed by this test');
            }

            public function findProfileById(string $id): ?UserProfile
            {
                throw new LogicException('not needed by this test');
            }

            public function documentIsTakenByAnother(Document $document, string $ownerId): bool
            {
                throw new LogicException('not needed by this test');
            }

            public function updateProfile(string $id, ProfileChanges $changes): UserProfile
            {
                throw new LogicException('not needed by this test');
            }

            public function replacePlatformRoles(string $userId, array $roles): UserIdentity
            {
                throw new LogicException('not needed by this test');
            }
        };

        (new RegisterUser($repositorio, $this->hasher()))
            ->handle('Joao Pedro', 'joao@shopmaster.test', 'senha-forte-123');
    }

    public function test_recusa_senha_fora_da_politica_antes_de_tocar_no_repositorio(): void
    {
        $repositorio = $this->repositorio();

        try {
            (new RegisterUser($repositorio, $this->hasher()))
                ->handle('Joao Pedro', 'joao@shopmaster.test', 'curta');
            $this->fail('Esperava InvalidPassword.');
        } catch (InvalidPassword) {
            $this->assertNull($repositorio->gravado, 'Nada deveria ter sido gravado.');
        }
    }

    public function test_recusa_email_malformado(): void
    {
        $this->expectException(InvalidEmailAddress::class);

        $this->useCase()->handle('Joao Pedro', 'joao-arroba-shopmaster', 'senha-forte-123');
    }

    // -----------------------------------------------------------------
    // Fakes escritos a mao — sao possiveis porque as portas sao pequenas.
    // -----------------------------------------------------------------

    /** @param list<string> $emailsExistentes */
    private function useCase(array $emailsExistentes = []): RegisterUser
    {
        return new RegisterUser($this->repositorio($emailsExistentes), $this->hasher());
    }

    /** @param list<string> $emailsExistentes */
    private function repositorio(array $emailsExistentes = []): UserRepository
    {
        return new class($emailsExistentes) implements UserRepository
        {
            public ?NewUser $gravado = null;

            /** @param list<string> $emailsExistentes */
            public function __construct(private array $emailsExistentes) {}

            public function existsByEmail(EmailAddress $email): bool
            {
                return in_array($email->value(), $this->emailsExistentes, strict: true);
            }

            public function add(NewUser $user): RegisteredUser
            {
                $this->gravado = $user;

                return new RegisteredUser(
                    id: '01a07e4a-a020-73a1-bd0e-6bac810e4a44',
                    name: $user->name,
                    email: $user->email,
                    roles: $user->roles,
                    registeredAt: new DateTimeImmutable('2026-09-07 12:00:00'),
                );
            }

            public function findCredentialsByEmail(EmailAddress $email): ?UserCredentials
            {
                throw new LogicException('not needed by this test');
            }

            public function findIdentityById(string $id): ?UserIdentity
            {
                throw new LogicException('not needed by this test');
            }

            public function findProfileById(string $id): ?UserProfile
            {
                throw new LogicException('not needed by this test');
            }

            public function documentIsTakenByAnother(Document $document, string $ownerId): bool
            {
                throw new LogicException('not needed by this test');
            }

            public function updateProfile(string $id, ProfileChanges $changes): UserProfile
            {
                throw new LogicException('not needed by this test');
            }

            public function replacePlatformRoles(string $userId, array $roles): UserIdentity
            {
                throw new LogicException('not needed by this test');
            }
        };
    }

    private function hasher(): PasswordHasher
    {
        return new class implements PasswordHasher
        {
            public int $chamadas = 0;

            public function hash(PlainPassword $plain): HashedPassword
            {
                $this->chamadas++;

                return HashedPassword::fromHash('hash-de:'.$plain->value());
            }

            public function verify(PlainPassword $plain, ?HashedPassword $hashed): bool
            {
                throw new LogicException('not needed by this test');
            }
        };
    }
}
