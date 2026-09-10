<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Requests;

use Identity\Domain\DocumentType;
use Identity\Domain\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH parcial: tudo é `sometimes`, então o que não vem não é tocado.
 *
 * `document` e `document_type` são `required_with` um do outro. Um documento
 * sem tipo é um valor que ninguém sabe validar; um tipo sem valor não é nada.
 * A mesma dupla é garantida no banco por um CHECK, para o caso de alguém
 * escrever por fora.
 */
final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:'.PersonName::MAX_LENGTH],
            // `nullable` de propósito: `"phone": null` é o pedido explícito de
            // remoção (RFC 7396). Omitir o campo continua significando
            // "não mexa".
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            // Sem `sometimes` nos dois: `sometimes` só aplica as regras se o
            // campo VIER, e o caso a pegar é justamente o campo que faltou.
            // `required_with` sozinho já se comporta como opcional quando
            // nenhum dos dois é enviado.
            'document' => ['required_with:document_type', 'string', 'max:20'],
            'document_type' => ['required_with:document', Rule::enum(DocumentType::class)],

            // RF-005: o e-mail não se troca no v1. Recusar explicitamente é
            // melhor do que ignorar em silêncio — quem tentou precisa saber
            // que não funcionou, senão sai achando que trocou.
            'email' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.prohibited' => 'O e-mail nao pode ser alterado.',
        ];
    }
}
