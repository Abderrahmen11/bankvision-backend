<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('id') ?? $this->route('customer');

        return [
            'full_name'               => ['sometimes', 'string', 'max:255'],
            'email'                   => ['sometimes', 'email', Rule::unique('customers', 'email')->ignore($customerId)],
            'phone'                   => ['sometimes', 'string', 'max:20'],
            'address'                 => ['nullable', 'string'],
            'city'                    => ['nullable', 'string', 'max:100'],
            'customer_type'           => ['sometimes', 'in:premium,regular,business'],
            'kyc_status'              => ['sometimes', 'in:verified,pending,expired'],
            'risk_level'              => ['sometimes', 'in:low,medium,high'],
            'branch_id'               => ['sometimes', 'exists:branches,id'],
            'relationship_manager_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
