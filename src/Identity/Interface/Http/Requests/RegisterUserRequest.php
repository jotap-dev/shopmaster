<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Requests;

use Closure;
use Identity\Domain\EmailAddress;
use Identity\Domain\PersonName;
use Identity\Domain\PlainPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de entrada do RF-001.
 *
 * Os limites vêm das constantes do domínio, não de números repetidos aqui:
 * mudar a política de senha é mexer no VO, e esta camada acompanha sozinha.
 *
 * Isto **não** substitui a validação do domínio — é a camada que produz
 * mensagem boa por campo. O VO continua sendo a última linha, para o caso de
 * outra rota vir a aceitar senha e esquecer alguma regra.
 */
final class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.PersonName::MAX_LENGTH],

            'email' => ['required', 'string', 'email:rfc', 'max:'.EmailAddress::MAX_LENGTH],

            'password' => [
                'required',
                'string',
                'min:'.PlainPassword::MIN_LENGTH,

                // `max:` do Laravel conta CARACTERES; o limite do bcrypt é em
                // BYTES. Sem esta regra, 40 emojis (40 caracteres, 160 bytes)
                // passariam, e o bcrypt truncaria a senha em silêncio.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > PlainPassword::MAX_BYTES) {
                        $fail('A senha excede o limite de '.PlainPassword::MAX_BYTES.' bytes.');
                    }
                },
            ],
        ];
    }
}
