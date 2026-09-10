<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * Telefone brasileiro: DDD + 8 (fixo) ou 9 (celular) dígitos.
 *
 * Guarda **só os dígitos**. Formatação é responsabilidade da tela: máscara
 * gravada faz o mesmo telefone virar várias linhas diferentes e inviabiliza
 * qualquer busca por ele.
 *
 * Só Brasil no v1, decisão registrada em docs/requisitos.md. Afrouxar para
 * E.164 depois é trocar a validação aqui — nenhum outro lugar precisa saber.
 */
final readonly class PhoneNumber
{
    /**
     * Os DDDs que existem de fato. A lista importa: sem ela, `00` e `99999`
     * passariam, e um telefone que ninguém consegue discar é pior do que
     * campo vazio — dá a impressão de que há contato.
     *
     * @var list<int>
     */
    private const DDDS = [
        11, 12, 13, 14, 15, 16, 17, 18, 19,
        21, 22, 24, 27, 28,
        31, 32, 33, 34, 35, 37, 38,
        41, 42, 43, 44, 45, 46, 47, 48, 49,
        51, 53, 54, 55,
        61, 62, 63, 64, 65, 66, 67, 68, 69,
        71, 73, 74, 75, 77, 79,
        81, 82, 83, 84, 85, 86, 87, 88, 89,
        91, 92, 93, 94, 95, 96, 97, 98, 99,
    ];

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $digitos = (string) preg_replace('/\D/', '', $value);

        // `+55` é o que vem de teclado internacional e de contato colado do
        // celular. Descartar o código do país é normalizar, não perder dado:
        // o v1 é só Brasil.
        if (strlen($digitos) > 11 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        if (strlen($digitos) !== 10 && strlen($digitos) !== 11) {
            throw InvalidPhoneNumber::wrongLength();
        }

        $ddd = substr($digitos, 0, 2);

        if (! in_array((int) $ddd, self::DDDS, strict: true)) {
            throw InvalidPhoneNumber::unknownAreaCode($ddd);
        }

        if (strlen($digitos) === 11 && $digitos[2] !== '9') {
            throw InvalidPhoneNumber::mobileMustStartWithNine();
        }

        return new self($digitos);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isMobile(): bool
    {
        return strlen($this->value) === 11;
    }

    public function formatted(): string
    {
        $ddd = substr($this->value, 0, 2);
        $numero = substr($this->value, 2);
        $corte = $this->isMobile() ? 5 : 4;

        return sprintf('(%s) %s-%s', $ddd, substr($numero, 0, $corte), substr($numero, $corte));
    }
}
