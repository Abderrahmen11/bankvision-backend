<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_name' => ['sometimes', 'string', 'max:255'],
            'address'     => ['nullable', 'string'],
            'city'        => ['nullable', 'string', 'max:100'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'status'      => ['sometimes', 'in:active,inactive,under_renovation'],
            'manager_id'  => ['nullable', 'exists:users,id'],
        ];
    }
}
