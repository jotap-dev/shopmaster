<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * O tipo viaja **dentro** do token, assinado.
 *
 * Sem ele, um refresh token — que vive 30 dias — seria aceito como access
 * token, e a vida curta do access (a única defesa contra um token vazado,
 * já que ele não é revogável) deixaria de existir. É a confusão de tipo de
 * token, e ela só se evita declarando o tipo no payload e conferindo no uso.
 */
enum TokenType: string
{
    case Access = 'access';
    case Refresh = 'refresh';
}
