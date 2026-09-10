<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * O tipo é o que decide **como validar** o valor.
 *
 * Guardar os dois separados (tipo + valor) em vez de adivinhar pelo formato
 * evita a ambiguidade real: um CPF e uma CNH têm os dois 11 dígitos, e o
 * mesmo número pode ser válido como um e inválido como o outro.
 *
 * Os três têm dígito verificador conferido. Passaporte foi deliberadamente
 * deixado de fora: não tem checksum nenhum — nem o brasileiro, nem os
 * estrangeiros —, então aceitá-lo seria dar aparência de verificação a um
 * campo que ninguém consegue verificar.
 */
enum DocumentType: string
{
    case Cpf = 'cpf';
    case Cnh = 'cnh';
    case Cnpj = 'cnpj';
}
