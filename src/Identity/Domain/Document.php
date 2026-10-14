<?php

declare(strict_types=1);

namespace Identity\Domain;

use JsonSerializable;

/**
 * Documento de identificação: o tipo e o valor, sempre juntos.
 *
 * O valor é guardado **normalizado** — só dígitos para CPF e CNH, maiúsculas
 * sem máscara para CNPJ. Gravar a máscara faria o mesmo documento virar
 * linhas diferentes no banco e arruinaria a constraint de unicidade.
 */
final readonly class Document implements JsonSerializable
{
    private function __construct(
        public DocumentType $type,
        public string $value,
    ) {}

    public static function fromString(DocumentType $type, string $value): self
    {
        $normalizado = match ($type) {
            DocumentType::Cpf, DocumentType::Cnh => preg_replace('/\D/', '', $value) ?? '',

            // No CNPJ só a máscara é removida — nunca "tudo que não é
            // esperado". Desde o formato alfanumérico, letra é conteúdo: se
            // um caractere estranho fosse descartado em silêncio, o valor
            // encurtaria e o erro sairia como "tamanho errado", escondendo o
            // que de fato aconteceu.
            DocumentType::Cnpj => strtoupper((string) preg_replace('/[.\/\-\s]/', '', $value)),
        };

        if ($normalizado === '') {
            throw InvalidDocument::empty($type);
        }

        match ($type) {
            DocumentType::Cpf => self::assertCpf($normalizado),
            DocumentType::Cnh => self::assertCnh($normalizado),
            DocumentType::Cnpj => self::assertCnpj($normalizado),
        };

        return new self($type, $normalizado);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->value === $other->value;
    }

    /**
     * O valor com o miolo escondido: `529******25`.
     *
     * Mantém as pontas de propósito. Num log de suporte, o que se quer saber
     * é "é este documento mesmo?" — as pontas respondem isso sem entregar o
     * número, que é o que um `[REDACTED]` completo também não faria.
     */
    public function masked(): string
    {
        $tamanho = strlen($this->value);

        if ($tamanho <= 5) {
            return str_repeat('*', $tamanho);
        }

        return substr($this->value, 0, 3)
            .str_repeat('*', $tamanho - 5)
            .substr($this->value, -2);
    }

    /**
     * CPF e CNPJ são dado pessoal (LGPD) e não podem cair em arquivo de log.
     *
     * Sem isto, um `Log::error(..., ['perfil' => $perfil])` ou uma exceção
     * que carregue o objeto no contexto grava o documento inteiro — e log
     * não tem rotação, vai para backup e fica lá.
     *
     * Note que a API **não** é afetada: o `UserProfileResource` lê a
     * propriedade `->value` direto, e o titular continua vendo o próprio
     * documento por inteiro.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['type' => $this->type->value, 'value' => $this->masked()];
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return ['type' => $this->type->value, 'value' => $this->masked()];
    }

    /**
     * CPF: dois dígitos verificadores em módulo 11, pesos decrescentes.
     */
    private static function assertCpf(string $cpf): void
    {
        if (strlen($cpf) !== 11) {
            throw InvalidDocument::wrongFormat(DocumentType::Cpf);
        }

        // 111.111.111-11 e os outros dez repetidos PASSAM no cálculo do
        // dígito verificador — a aritmética fecha. Sem esta guarda em
        // separado, são os primeiros palpites de quem quer burlar o cadastro.
        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            throw InvalidDocument::failsCheckDigit(DocumentType::Cpf);
        }

        foreach ([9, 10] as $posicao) {
            $soma = 0;
            $peso = $posicao + 1;

            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * $peso--;
            }

            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $cpf[$posicao] !== $digito) {
                throw InvalidDocument::failsCheckDigit(DocumentType::Cpf);
            }
        }
    }

    /**
     * CNH: dois módulos 11 sucessivos, com uma correção que quase ninguém
     * lembra.
     *
     * O primeiro DV usa pesos **decrescentes** de 9 a 1; o segundo, pesos
     * **crescentes** de 1 a 9. Quando o resto do primeiro passa de 9, ele
     * vira 0 e o segundo sofre um ajuste de -2 (ou +9, se ficaria negativo).
     * Sem essa correção a validação parece funcionar — falha só na fatia de
     * números em que o primeiro resto estoura, e passa despercebida.
     *
     * @see https://siga0984.wordpress.com/2019/05/01/algoritmos-validacao-de-cnh/
     */
    private static function assertCnh(string $cnh): void
    {
        if (strlen($cnh) !== 11) {
            throw InvalidDocument::wrongFormat(DocumentType::Cnh);
        }

        if (preg_match('/^(\d)\1{10}$/', $cnh) === 1) {
            throw InvalidDocument::failsCheckDigit(DocumentType::Cnh);
        }

        [$primeiro, $segundo] = self::digitosDaCnh(substr($cnh, 0, 9));

        if ((int) $cnh[9] !== $primeiro || (int) $cnh[10] !== $segundo) {
            throw InvalidDocument::failsCheckDigit(DocumentType::Cnh);
        }
    }

    /**
     * Os dois dígitos verificadores de uma base de 9 dígitos.
     *
     * Exposto para o teste conseguir montar uma CNH válida e depois adulterar
     * cada posição — provar que mexer em qualquer dígito quebra é o que
     * garante que os dois verificadores estão mesmo sendo conferidos.
     *
     * @return array{int, int}
     */
    public static function digitosDaCnh(string $base): array
    {
        $soma = 0;
        for ($i = 0, $peso = 9; $i < 9; $i++, $peso--) {
            $soma += (int) $base[$i] * $peso;
        }

        $primeiro = $soma % 11;
        $estourou = $primeiro > 9;

        if ($estourou) {
            $primeiro = 0;
        }

        $soma = 0;
        for ($i = 0, $peso = 1; $i < 9; $i++, $peso++) {
            $soma += (int) $base[$i] * $peso;
        }

        $segundo = $soma % 11;

        if ($estourou) {
            $segundo = $segundo - 2 < 0 ? $segundo + 9 : $segundo - 2;
        }

        if ($segundo > 9) {
            $segundo = 0;
        }

        return [$primeiro, $segundo];
    }

    /** Monta uma CNH válida a partir de uma base — só para teste. */
    public static function cnhValidaParaTeste(string $base): string
    {
        return $base.implode('', self::digitosDaCnh($base));
    }

    /**
     * CNPJ no formato **alfanumérico** (IN RFB 2.229/2024).
     *
     * São 14 posições: 12 alfanuméricas de base + 2 dígitos verificadores,
     * que continuam **sempre numéricos**.
     *
     * A mudança que importa está na conversão: cada caractere vira o valor
     * ASCII dele **menos 48**. Para dígitos isso devolve o próprio número
     * ('0' = 48 → 0), e é por isso que o formato novo é superconjunto do
     * antigo — todo CNPJ numérico já emitido continua válido pelo mesmo
     * cálculo. Para letras, 'A' = 65 → 17, 'B' → 18, e assim por diante.
     *
     * Os pesos são os de sempre: 5,4,3,2,9,8,7,6,5,4,3,2 no primeiro dígito;
     * 6,5,4,3,2,9,8,7,6,5,4,3,2 no segundo, já incluindo o primeiro.
     *
     * Aceita qualquer letra de A a Z. A Receita evita emitir I, O, Q e F por
     * se confundirem com 1, 0 e 5, mas isso é diretriz de **emissão**: um
     * validador que as recusasse rejeitaria um CNPJ legítimo se a regra
     * mudar, e o dígito verificador já barra o que é inventado.
     *
     * @see https://www.nfe.fazenda.gov.br/portal/ — Nota Técnica 2025.001
     */
    private static function assertCnpj(string $cnpj): void
    {
        if (preg_match('/^[A-Z0-9]{12}\d{2}$/', $cnpj) !== 1) {
            throw InvalidDocument::wrongFormat(DocumentType::Cnpj);
        }

        // 14 zeros PASSAM no módulo 11: a soma dá zero, o resto dá zero e os
        // dois dígitos saem 0. Sem esta guarda, viraria um CNPJ "válido".
        if (preg_match('/^(.)\1{13}$/', $cnpj) === 1) {
            throw InvalidDocument::failsCheckDigit(DocumentType::Cnpj);
        }

        [$primeiro, $segundo] = self::digitosDoCnpj(substr($cnpj, 0, 12));

        if ((int) $cnpj[12] !== $primeiro || (int) $cnpj[13] !== $segundo) {
            throw InvalidDocument::failsCheckDigit(DocumentType::Cnpj);
        }
    }

    /**
     * Os dois dígitos verificadores de uma base de 12 caracteres.
     *
     * @return array{int, int}
     */
    public static function digitosDoCnpj(string $base): array
    {
        $primeiro = self::moduloOnzeDoCnpj($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $segundo = self::moduloOnzeDoCnpj($base.$primeiro, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return [$primeiro, $segundo];
    }

    /** @param list<int> $pesos */
    private static function moduloOnzeDoCnpj(string $caracteres, array $pesos): int
    {
        $soma = 0;

        foreach ($pesos as $posicao => $peso) {
            // A conversão do formato alfanumérico: valor ASCII menos 48.
            $soma += (ord($caracteres[$posicao]) - 48) * $peso;
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }

    /** Monta um CNPJ válido a partir de uma base — só para teste. */
    public static function cnpjValidoParaTeste(string $base): string
    {
        return $base.implode('', self::digitosDoCnpj($base));
    }
}
