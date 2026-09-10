<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Support;

use Identity\Application\UserRepository;
use Identity\Domain\Document;
use Identity\Domain\DocumentType;
use Identity\Domain\EmailAddress;
use Identity\Domain\NewUser;
use Identity\Domain\PersonName;
use Identity\Domain\PhoneNumber;
use Identity\Domain\ProfileChanges;
use Identity\Domain\RegisteredUser;
use Identity\Domain\UserCredentials;
use Identity\Domain\UserIdentity;
use Identity\Domain\UserProfile;
use LogicException;

final class FakeProfileRepository implements UserRepository
{
    public int $gravacoes = 0;

    private UserProfile $perfil;

    public function __construct(
        ?string $phone = null,
        ?string $document = null,
        private bool $documentoDeOutro = false,
    ) {
        $this->perfil = new UserProfile(
            id: 'user-1',
            name: PersonName::fromString('Joao Pedro'),
            email: EmailAddress::fromString('joao@shopmaster.test'),
            phone: $phone === null ? null : PhoneNumber::fromString($phone),
            document: $document === null ? null : Document::fromString(DocumentType::Cpf, $document),
        );
    }

    public function findProfileById(string $id): ?UserProfile
    {
        return $id === 'user-1' ? $this->perfil : null;
    }

    public function documentIsTakenByAnother(Document $document, string $ownerId): bool
    {
        return $this->documentoDeOutro;
    }

    public function updateProfile(string $id, ProfileChanges $changes): UserProfile
    {
        $this->gravacoes++;

        $this->perfil = new UserProfile(
            id: $this->perfil->id,
            name: $changes->name ?? $this->perfil->name,
            email: $this->perfil->email,
            phone: $changes->removesPhone ? null : ($changes->phone ?? $this->perfil->phone),
            document: $changes->document ?? $this->perfil->document,
        );

        return $this->perfil;
    }

    public function existsByEmail(EmailAddress $email): bool
    {
        throw new LogicException('not needed by this test');
    }

    public function add(NewUser $user): RegisteredUser
    {
        throw new LogicException('not needed by this test');
    }

    public function findCredentialsByEmail(EmailAddress $email): ?UserCredentials
    {
        throw new LogicException('not needed by this test');
    }

    public function findIdentityById(string $id): ?UserIdentity
    {
        throw new LogicException('not needed by this test');
    }

    public function replacePlatformRoles(string $userId, array $roles): UserIdentity
    {
        throw new LogicException('not needed by this test');
    }
}
