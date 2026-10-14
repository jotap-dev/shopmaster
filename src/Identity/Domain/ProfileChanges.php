<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * Um PATCH: só o que veio na requisição.
 *
 * `null` num campo significa **não informado**, não "limpar" — sem essa
 * distinção, mandar só o telefone apagaria o nome.
 *
 * Remover é uma intenção **separada**, e por isso tem sinalizador próprio.
 * É o que o RFC 7396 (JSON Merge Patch) expressa com `null` explícito no
 * corpo, e é o que dá ao titular o direito de retirar um dado de contato que
 * forneceu.
 *
 * Note o que não existe aqui: e-mail. Não é esquecimento — o RF-005 diz que
 * ele não se troca no v1, e o campo simplesmente não tem por onde entrar.
 */
final readonly class ProfileChanges
{
    public function __construct(
        public ?PersonName $name = null,
        public ?PhoneNumber $phone = null,
        public ?Document $document = null,
        public bool $removesPhone = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->name === null
            && $this->phone === null
            && $this->document === null
            && ! $this->removesPhone;
    }
}
