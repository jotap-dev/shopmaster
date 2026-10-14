<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * O par emitido a cada login e a cada refresh.
 *
 * Vêm sempre juntos porque o refresh é de uso único (RF-004): quem troca um
 * refresh recebe um par novo, e o anterior morre. Emitir só o access deixaria
 * o cliente com um refresh já queimado.
 */
final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {}
}
