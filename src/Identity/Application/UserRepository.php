<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\Document;
use Identity\Domain\EmailAddress;
use Identity\Domain\NewUser;
use Identity\Domain\PlatformRole;
use Identity\Domain\ProfileChanges;
use Identity\Domain\RegisteredUser;
use Identity\Domain\UserCredentials;
use Identity\Domain\UserIdentity;
use Identity\Domain\UserProfile;

interface UserRepository
{
    public function existsByEmail(EmailAddress $email): bool;

    /**
     * Grava o usuário e devolve ele com id e data.
     *
     * **Contrato:** lança `EmailAlreadyRegistered` se a unicidade do e-mail
     * for violada. A verificação prévia com `existsByEmail()` é uma cortesia,
     * não uma garantia — entre a consulta e o INSERT cabe outro cadastro do
     * mesmo e-mail. Quem garante é a constraint `UNIQUE` do banco, e é
     * responsabilidade do adapter traduzir essa violação para cá.
     */
    public function add(NewUser $user): RegisteredUser;

    /** Caminho do login: é o único lugar que carrega o hash da senha. */
    public function findCredentialsByEmail(EmailAddress $email): ?UserCredentials;

    /**
     * Caminho do refresh: recarrega quem o token diz ser, **sem** o hash.
     *
     * É recarregado do banco, e não reaproveitado dos claims, para que
     * mudança de papel valha a partir do próximo refresh — e para que um
     * token de conta apagada deixe de funcionar.
     */
    public function findIdentityById(string $id): ?UserIdentity;

    /** Caminho de "minha conta": identidade + contato, sem credencial. */
    public function findProfileById(string $id): ?UserProfile;

    /** O documento pertence a **outra** conta? Fecha a unicidade do RF-005. */
    public function documentIsTakenByAnother(Document $document, string $ownerId): bool;

    /**
     * Aplica só os campos informados e devolve a conta atualizada.
     *
     * **Contrato:** lança `DocumentAlreadyInUse` se a unicidade do documento
     * for violada. Como no cadastro, a verificação prévia é cortesia — quem
     * garante é a constraint do banco, e traduzi-la é do adapter.
     */
    public function updateProfile(string $id, ProfileChanges $changes): UserProfile;

    /**
     * Substitui o conjunto inteiro de papéis de plataforma (RF-007).
     *
     * Roda numa transação: apaga os atuais e grava os novos. Quem garante
     * que o usuário existe é o use case — o adapter só escreve.
     *
     * @param  list<PlatformRole>  $roles
     */
    public function replacePlatformRoles(string $userId, array $roles): UserIdentity;
}
