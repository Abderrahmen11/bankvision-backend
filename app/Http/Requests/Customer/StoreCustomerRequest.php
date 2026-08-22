<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'               => ['required', 'string', 'max:255'],
            'email'                   => ['required', 'email', 'unique:customers,email'],
            'phone'                   => ['required', 'string', 'max:20'],
            'address'                 => ['nullable', 'string'],
            'city'                    => ['nullable', 'string', 'max:100'],
            'customer_type'           => ['required', 'in:premium,regular,business'],
            'kyc_status'              => ['sometimes', 'in:verified,pending,expired'],
            'risk_level'              => ['sometimes', 'in:low,medium,high'],
            'registration_date'       => ['sometimes', 'date'],
            'branch_id'               => ['required', 'exists:branches,id'],
            'relationship_manager_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
