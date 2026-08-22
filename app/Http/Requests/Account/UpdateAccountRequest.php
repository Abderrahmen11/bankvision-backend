<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'        => ['sometimes', 'in:active,frozen,closed'],
            'interest_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
