<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Support;

use Identity\Application\UserRepository;
use Identity\Domain\Document;
use Identity\Domain\EmailAddress;
use Identity\Domain\HashedPassword;
use Identity\Domain\NewUser;
use Identity\Domain\PersonName;
use Identity\Domain\PlatformRole;
use Identity\Domain\ProfileChanges;
use Identity\Domain\RegisteredUser;
use Identity\Domain\UserCredentials;
use Identity\Domain\UserIdentity;
use Identity\Domain\UserProfile;
use LogicException;

final class FakeUserRepository implements UserRepository
{
    public int $buscasPorEmail = 0;

    public int $buscasPorId = 0;

    /** @param list<PlatformRole> $roles */
    public static function com(
        string $id = 'user-1',
        string $email = 'joao@shopmaster.test',
        string $hash = 'hash-de:senha-forte-123',
        array $roles = [PlatformRole::Buyer],
    ): self {
        return new self($id, $email, $hash, $roles);
    }

    public static function vazio(): self
    {
        return new self(null, null, null, []);
    }

    /** @param list<PlatformRole> $roles */
    private function __construct(
        private ?string $id,
        private ?string $email,
        private ?string $hash,
        private array $roles,
    ) {}

    /** @param list<PlatformRole> $roles */
    public function comPapeis(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function findCredentialsByEmail(EmailAddress $email): ?UserCredentials
    {
        $this->buscasPorEmail++;

        if ($this->email === null || $email->value() !== $this->email) {
            return null;
        }

        return new UserCredentials($this->identidade(), HashedPassword::fromHash((string) $this->hash));
    }

    public function findIdentityById(string $id): ?UserIdentity
    {
        $this->buscasPorId++;

        return $this->id === $id ? $this->identidade() : null;
    }

    public function existsByEmail(EmailAddress $email): bool
    {
        throw new LogicException('not needed by this test');
    }

    public function add(NewUser $user): RegisteredUser
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
        $this->roles = $roles;

        return $this->identidade();
    }

    private function identidade(): UserIdentity
    {
        return new UserIdentity(
            id: (string) $this->id,
            name: PersonName::fromString('Joao Pedro'),
            email: EmailAddress::fromString((string) $this->email),
            roles: $this->roles,
        );
    }
}
