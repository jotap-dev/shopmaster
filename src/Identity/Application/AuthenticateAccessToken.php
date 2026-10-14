<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\AuthenticatedUser;
use Identity\Domain\TokenType;

/**
 * O que o middleware `auth.token` roda a cada requisição protegida.
 *
 * **Não recebe repositório, e isso é decisão de projeto.** Autenticar uma
 * requisição não toca no banco: identidade e papéis de plataforma vêm dos
 * claims assinados (RULE 7). O preço está escrito em docs/auth.md — revogar
 * um papel só tem efeito quando o access token expira —, e é o que mantém a
 * autenticação stateless e barata em toda rota protegida da API.
 */
final class AuthenticateAccessToken
{
    public function __construct(private TokenIssuer $tokens) {}

    /**
     * @throws InvalidToken
     * @throws ExpiredToken
     */
    public function handle(string $accessToken): AuthenticatedUser
    {
        $claims = $this->tokens->parse($accessToken);

        if (! $claims->isOfType(TokenType::Access)) {
            throw InvalidToken::wrongType(TokenType::Access);
        }

        return new AuthenticatedUser($claims->subject, $claims->email, $claims->roles);
    }
}
