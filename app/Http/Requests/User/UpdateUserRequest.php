<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id') ?? $this->route('user');

        return [
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'email'     => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password'  => ['sometimes', 'nullable', 'string', 'min:8'],
            'role'      => ['sometimes', 'required', 'string', 'in:' . implode(',', Role::values())],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status'    => ['sometimes', 'required', 'string', 'in:pending,active,suspended'],
            'phone'     => ['nullable', 'string', 'max:50'],
        ];
    }
}
