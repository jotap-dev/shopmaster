<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\AuthenticatedSession;
use Identity\Domain\EmailAddress;
use Identity\Domain\InvalidEmailAddress;
use Identity\Domain\InvalidPassword;
use Identity\Domain\PlainPassword;

/**
 * RF-003 — login por e-mail e senha.
 *
 * Todo caminho de falha sai por `InvalidCredentials`, com a **mesma**
 * mensagem (RF-008). E-mail inexistente, e-mail malformado, senha errada ou
 * senha que nem passaria na política de cadastro: para quem está do lado de
 * fora, é tudo indistinguível. Distinguir qualquer um deles transformaria
 * esta rota num verificador de "esta conta existe?", e uma base inteira de
 * e-mails sai daí.
 */
final class Authenticate
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private TokenIssuer $tokens,
    ) {}

    /** @throws InvalidCredentials */
    public function handle(string $email, string $password): AuthenticatedSession
    {
        try {
            $endereco = EmailAddress::fromString($email);
            $senha = PlainPassword::fromString($password);
        } catch (InvalidEmailAddress|InvalidPassword) {
            // As invariantes do domínio existem para o CADASTRO. No login,
            // entrada que não forma uma credencial válida é simplesmente uma
            // credencial errada — e tem de responder igual às outras.
            throw InvalidCredentials::create();
        }

        $credenciais = $this->users->findCredentialsByEmail($endereco);

        // Sem usuário, passa `null` em vez de retornar cedo: o adapter queima
        // o mesmo tempo de um bcrypt de verdade. Mensagem igual não basta se
        // o relógio denuncia qual dos dois casos aconteceu.
        if (! $this->hasher->verify($senha, $credenciais?->password)) {
            throw InvalidCredentials::create();
        }

        return new AuthenticatedSession(
            $credenciais->user,
            $this->tokens->issue($credenciais->user),
        );
    }
}
