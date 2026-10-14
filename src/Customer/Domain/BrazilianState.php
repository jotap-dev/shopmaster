<?php

declare(strict_types=1);

namespace Customer\Domain;

/**
 * Unidade federativa brasileira.
 *
 * Enum fechado: UF inventada passa pelo Form Request e cai aqui — a coluna
 * não tem CHECK de propósito, para o PHP continuar sendo a única porta.
 */
enum BrazilianState: string
{
    case AC = 'AC';
    case AL = 'AL';
    case AP = 'AP';
    case AM = 'AM';
    case BA = 'BA';
    case CE = 'CE';
    case DF = 'DF';
    case ES = 'ES';
    case GO = 'GO';
    case MA = 'MA';
    case MT = 'MT';
    case MS = 'MS';
    case MG = 'MG';
    case PA = 'PA';
    case PB = 'PB';
    case PR = 'PR';
    case PE = 'PE';
    case PI = 'PI';
    case RJ = 'RJ';
    case RN = 'RN';
    case RS = 'RS';
    case RO = 'RO';
    case RR = 'RR';
    case SC = 'SC';
    case SP = 'SP';
    case SE = 'SE';
    case TO = 'TO';

    public static function fromString(string $value): self
    {
        $normalizado = strtoupper(trim($value));

        return self::tryFrom($normalizado) ?? throw InvalidBrazilianState::unknown($value);
    }
}
