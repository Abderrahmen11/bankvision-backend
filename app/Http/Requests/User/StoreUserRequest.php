<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8'],
            'role'      => ['required', 'string', 'in:' . implode(',', Role::values())],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status'    => ['sometimes', 'string', 'in:pending,active,suspended'],
            'phone'     => ['nullable', 'string', 'max:50'],
        ];
    }
}
