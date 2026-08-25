<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class IndexCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'type'           => ['sometimes', 'nullable', 'string', 'in:premium,regular,business'],
            'kyc_status'     => ['sometimes', 'nullable', 'string', 'in:verified,pending,expired'],
            'risk_level'     => ['sometimes', 'nullable', 'string', 'in:low,medium,high'],
            'branch_id'      => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'sort_by'        => ['sometimes', 'nullable', 'string', 'in:registration_date,created_at,full_name,customer_number'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'direction'      => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'order'          => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'page'           => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page'       => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
