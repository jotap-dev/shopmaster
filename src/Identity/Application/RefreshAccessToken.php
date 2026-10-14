<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\AuthenticatedSession;
use Identity\Domain\TokenType;

/**
 * RF-004 — troca do refresh token, que é de uso único.
 *
 * Uso único significa que um refresh roubado só serve uma vez. E, se o dono
 * legítimo usar o dele primeiro, o do atacante é recusado — é o sinal de que
 * houve vazamento.
 */
final class RefreshAccessToken
{
    public function __construct(
        private UserRepository $users,
        private TokenIssuer $tokens,
        private RefreshTokenBlacklist $blacklist,
    ) {}

    /**
     * @throws InvalidToken
     * @throws ExpiredToken
     * @throws RefreshTokenAlreadyUsed
     * @throws AuthUserNotFound
     */
    public function handle(string $refreshToken): AuthenticatedSession
    {
        $claims = $this->tokens->parse($refreshToken);

        // Um access token tem assinatura igualmente válida — o que o separa é
        // o tipo declarado no payload. Sem esta conferência, um access
        // interceptado renderia um refresh novo, e daí acesso indefinido.
        if (! $claims->isOfType(TokenType::Refresh)) {
            throw InvalidToken::wrongType(TokenType::Refresh);
        }

        if ($this->blacklist->isRevoked($claims->id)) {
            throw RefreshTokenAlreadyUsed::create();
        }

        // Revoga ANTES de emitir. Se a emissão falhar depois disto, o cliente
        // perde a sessão e refaz o login — chato, mas seguro. Na ordem
        // inversa, uma falha deixaria vivo um refresh que já foi entregue.
        $this->blacklist->revoke($claims->id, $claims->expiresAt);

        // Recarregado do banco, não reaproveitado dos claims: é aqui que uma
        // mudança de papel passa a valer, e é aqui que o token de uma conta
        // apagada para de funcionar.
        $usuario = $this->users->findIdentityById($claims->subject)
            ?? throw AuthUserNotFound::create();

        return new AuthenticatedSession($usuario, $this->tokens->issue($usuario));
    }
}
