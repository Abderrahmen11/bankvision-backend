<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'role'           => ['sometimes', 'nullable', 'string', 'in:' . implode(',', Role::values())],
            'status'         => ['sometimes', 'nullable', 'string', 'in:pending,active,suspended'],
            'branch_id'      => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'branch'         => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'sort_by'        => ['sometimes', 'nullable', 'string', 'in:name,role,created_at,last_login_at'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'page'           => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page'       => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
