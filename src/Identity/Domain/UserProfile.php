<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * A conta como o dono dela a vê: identidade e dados de contato.
 *
 * **Não carrega papéis, de propósito.** Papel é dado de autorização, não de
 * perfil, e quem responde "o que eu posso fazer agora" é o token — via
 * `GET /v1/auth/session`. O banco pode já ter um papel que o token ainda não
 * tem (ele só entra na próxima renovação), então um perfil que devolvesse
 * papéis estaria prometendo uma permissão que o middleware vai recusar.
 * Uma pergunta, uma fonte.
 *
 * Telefone e documento são anuláveis porque nascem vazios no cadastro
 * (RF-001 pede só nome, e-mail e senha) e são preenchidos depois.
 */
final readonly class UserProfile
{
    public function __construct(
        public string $id,
        public PersonName $name,
        public EmailAddress $email,
        public ?PhoneNumber $phone,
        public ?Document $document,
    ) {}

    public function hasDocument(): bool
    {
        return $this->document !== null;
    }
}
