<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do login — deliberadamente frouxa.
 *
 * Nada de `email:rfc`, nada de `min` na senha: a política de cadastro **não**
 * vale aqui. Um 422 dizendo "e-mail inválido" ou "senha curta demais" contaria
 * ao atacante que a entrada dele nem chegou a ser comparada, e ainda revelaria
 * a política vigente. Só se exige presença; o resto vira `invalid_credentials`
 * como qualquer outra credencial errada (RF-008).
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
