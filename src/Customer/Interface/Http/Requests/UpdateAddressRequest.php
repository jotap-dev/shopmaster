<?php

declare(strict_types=1);

namespace Customer\Interface\Http\Requests;

use Customer\Domain\BrazilianState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:40'],
            'recipient_name' => ['sometimes', 'string', 'max:120'],
            'street' => ['sometimes', 'string', 'max:200'],
            'number' => ['sometimes', 'string', 'max:20'],
            'complement' => ['sometimes', 'nullable', 'string', 'max:120'],
            'neighborhood' => ['sometimes', 'string', 'max:120'],
            'city' => ['sometimes', 'string', 'max:120'],
            'state' => ['sometimes', 'string', Rule::in(array_column(BrazilianState::cases(), 'value'))],
            'postal_code' => ['sometimes', 'string', 'max:9'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
