<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\Document;
use Identity\Domain\DocumentType;
use Identity\Domain\InvalidDocument;
use Identity\Domain\InvalidPersonName;
use Identity\Domain\InvalidPhoneNumber;
use Identity\Domain\PersonName;
use Identity\Domain\PhoneNumber;
use Identity\Domain\ProfileChanges;
use Identity\Domain\UserProfile;

/**
 * RF-005 — editar a própria conta.
 *
 * Não há parâmetro de e-mail, e isso é o requisito: no v1 o e-mail não se
 * troca. Deixá-lo de fora da assinatura é mais forte do que validá-lo e
 * recusar — não existe caminho por onde ele entre.
 */
final class UpdateMyProfile
{
    public function __construct(private UserRepository $users) {}

    /**
     * @throws ProfileNotFound
     * @throws InvalidDocument|InvalidPersonName|InvalidPhoneNumber
     * @throws DocumentAlreadySet
     * @throws DocumentAlreadyInUse
     */
    public function handle(
        string $userId,
        ?string $name = null,
        ?string $phone = null,
        bool $removePhone = false,
        ?string $document = null,
        ?string $documentType = null,
    ): UserProfile {
        $perfil = $this->users->findProfileById($userId) ?? throw ProfileNotFound::create();

        // Tudo validado antes de qualquer escrita: um nome válido seguido de
        // um documento inválido não pode gravar metade do PATCH.
        $mudancas = new ProfileChanges(
            name: $name === null ? null : PersonName::fromString($name),
            phone: $phone === null ? null : PhoneNumber::fromString($phone),
            document: $this->documento($document, $documentType),
            removesPhone: $removePhone,
        );

        if ($mudancas->isEmpty()) {
            return $perfil;
        }

        if ($mudancas->document !== null) {
            $this->garantirQuePodeDefinir($mudancas->document, $perfil);
        }

        return $this->users->updateProfile($userId, $mudancas);
    }

    private function documento(?string $valor, ?string $tipo): ?Document
    {
        if ($valor === null) {
            return null;
        }

        if ($tipo === null) {
            throw InvalidDocument::missingType();
        }

        $tipoDocumento = DocumentType::tryFrom($tipo) ?? throw InvalidDocument::unknownType($tipo);

        return Document::fromString($tipoDocumento, $valor);
    }

    private function garantirQuePodeDefinir(Document $novo, UserProfile $perfil): void
    {
        // Reenviar o MESMO documento não é erro: um PATCH com o formulário
        // inteiro precisa ser idempotente, senão editar o telefone falharia
        // só porque o documento veio junto, inalterado.
        if ($perfil->hasDocument() && ! $perfil->document?->equals($novo)) {
            throw DocumentAlreadySet::create();
        }

        if ($this->users->documentIsTakenByAnother($novo, $perfil->id)) {
            throw DocumentAlreadyInUse::create();
        }
    }
}
