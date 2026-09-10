<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\TokenClaims;
use Identity\Domain\TokenPair;
use Identity\Domain\UserIdentity;

interface TokenIssuer
{
    /** Emite o par sempre junto — ver TokenPair para o porquê. */
    public function issue(UserIdentity $user): TokenPair;

    /**
     * Abre e **confere** o token: assinatura, emissor e validade.
     *
     * @throws InvalidToken formato, assinatura ou emissor errados
     * @throws ExpiredToken assinatura válida, prazo vencido
     */
    public function parse(string $token): TokenClaims;
}
