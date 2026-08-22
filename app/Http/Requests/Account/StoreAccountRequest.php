<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'   => ['required', 'exists:customers,id'],
            'account_type'  => ['required', 'in:savings,checking,business'],
            'currency'      => ['sometimes', 'string', 'size:3'],
            'balance'       => ['sometimes', 'numeric', 'min:0', 'max:999999999.99'],
            'interest_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'opened_date'   => ['sometimes', 'date'],
        ];
    }
}
