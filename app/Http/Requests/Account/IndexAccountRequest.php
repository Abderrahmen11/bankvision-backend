<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class IndexAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_id'    => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'type'           => ['sometimes', 'nullable', 'string', 'in:savings,checking,business'],
            'account_type'   => ['sometimes', 'nullable', 'string', 'in:savings,checking,business'],
            'status'         => ['sometimes', 'nullable', 'string', 'in:active,frozen,closed'],
            'currency'       => ['sometimes', 'nullable', 'string', 'size:3'],
            'branch_id'      => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'balance_min'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'balance_max'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sort_by'        => ['sometimes', 'nullable', 'string', 'in:balance,opened_date,account_number,created_at,updated_at'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'page'           => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page'       => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
