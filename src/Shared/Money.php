<?php

declare(strict_types=1);

namespace Shared;

use InvalidArgumentException;

/**
 * Dinheiro como valor imutável: inteiro em centavos + moeda.
 *
 * Nunca `float`. `0.1 + 0.2 !== 0.3` em ponto flutuante, e num carrinho
 * com dez linhas isso vira um centavo de diferença entre o que a API
 * cobra e o que o cliente vê — o tipo de bug que só aparece em produção,
 * na reclamação de alguém.
 *
 * A moeda viaja junto do valor de propósito: somar BRL com USD é um erro
 * de programa, não um arredondamento, e estoura na hora.
 *
 * **Fronteira com o banco.** As colunas monetárias são `numeric(12,2)` —
 * decimal exato do Postgres, não `float` e não o tipo `money` (cuja escala
 * depende do `lc_monetary` do servidor e que não carrega qual moeda é).
 * O PHP não tem tipo decimal nativo, então a conversão acontece por
 * **string**, nunca por `float`: `fromDecimalString()` na leitura,
 * `toDecimalString()` na escrita. Este VO é o único ponto da aplicação onde
 * essa conversão pode acontecer.
 */
final readonly class Money
{
    private function __construct(
        private int $amountInCents,
        private string $currency,
    ) {}

    public static function fromCents(int $cents, string $currency = 'BRL'): self
    {
        if ($currency === '' || strlen($currency) !== 3) {
            throw new InvalidArgumentException("Moeda invalida: '{$currency}'. Esperado codigo ISO-4217 de 3 letras.");
        }

        return new self($cents, strtoupper($currency));
    }

    public static function zero(string $currency = 'BRL'): self
    {
        return self::fromCents(0, $currency);
    }

    /**
     * Lê um `numeric(12,2)` vindo do Postgres — que chega como string,
     * justamente para não perder exatidão no caminho.
     *
     * A conversão é feita sobre os dígitos, sem `(float)` e sem `round()`:
     * `(int) (19.99 * 100)` devolve 1998 em ponto flutuante, e esse centavo
     * perdido é o tipo de bug que só aparece no fechamento do mês.
     */
    public static function fromDecimalString(string $decimal, string $currency = 'BRL'): self
    {
        $decimal = trim($decimal);

        if (preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/', $decimal, $m) !== 1) {
            throw new InvalidArgumentException(
                "Valor monetario invalido: '{$decimal}'. Esperado decimal com ate 2 casas, como '199.90'."
            );
        }

        [, $sinal, $inteiro, $fracao] = $m + [3 => ''];

        $centavos = ((int) $inteiro) * 100 + (int) str_pad($fracao, 2, '0');

        return self::fromCents($sinal === '-' ? -$centavos : $centavos, $currency);
    }

    /** Escreve para um `numeric(12,2)`: sempre 2 casas, sempre string. */
    public function toDecimalString(): string
    {
        $sinal = $this->amountInCents < 0 ? '-' : '';
        $absoluto = abs($this->amountInCents);

        return sprintf('%s%d.%02d', $sinal, intdiv($absoluto, 100), $absoluto % 100);
    }

    public function cents(): int
    {
        return $this->amountInCents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }

    /**
     * Multiplica por uma quantidade inteira — o caso da linha de carrinho
     * (preço unitário x quantidade). Percentual de desconto é outra coisa,
     * e mora em Pricing, onde a regra de arredondamento é decisão de negócio.
     */
    public function times(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException("Quantidade nao pode ser negativa: {$quantity}.");
        }

        return new self($this->amountInCents * $quantity, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amountInCents === 0;
    }

    public function isNegative(): bool
    {
        return $this->amountInCents < 0;
    }

    public function equals(self $other): bool
    {
        return $this->amountInCents === $other->amountInCents
            && $this->currency === $other->currency;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountInCents > $other->amountInCents;
    }

    /** Formato de transporte na API: centavos + moeda, sem string formatada. */
    public function toArray(): array
    {
        return ['amount' => $this->amountInCents, 'currency' => $this->currency];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Nao e possivel operar {$this->currency} com {$other->currency}."
            );
        }
    }
}
