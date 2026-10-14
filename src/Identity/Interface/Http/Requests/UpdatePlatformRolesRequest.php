<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Requests;

use Identity\Domain\PlatformRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePlatformRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in(array_column(PlatformRole::cases(), 'value'))],
        ];
    }
}
