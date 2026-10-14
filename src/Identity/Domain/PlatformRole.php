<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * Papel na plataforma — o que o usuário é **globalmente**.
 *
 * São acumuláveis: quem vende também compra. Viajam num claim do token
 * porque o conjunto é pequeno e limitado.
 *
 * Não confundir com papel de **loja** (`owner`, `admin`, `finance`), que é
 * por loja, fica fora do token e pertence ao contexto `Store`. Ver a RULE 7
 * do CLAUDE.md — misturar os dois é o erro estrutural mais caro do projeto.
 */
enum PlatformRole: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case PlatformAdmin = 'platform_admin';
}
