<?php

declare(strict_types=1);

namespace Customer\Interface\Http\Requests;

use Customer\Domain\BrazilianState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAddressRequest extends FormRequest
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
            'recipient_name' => ['required', 'string', 'max:120'],
            'street' => ['required', 'string', 'max:200'],
            'number' => ['required', 'string', 'max:20'],
            'complement' => ['sometimes', 'nullable', 'string', 'max:120'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', Rule::in(array_column(BrazilianState::cases(), 'value'))],
            'postal_code' => ['required', 'string', 'max:9'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
