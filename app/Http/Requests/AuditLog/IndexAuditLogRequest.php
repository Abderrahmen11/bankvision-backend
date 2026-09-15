<?php

namespace App\Http\Requests\AuditLog;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'action'         => ['sometimes', 'nullable', 'string'],
            'table_name'     => ['sometimes', 'nullable', 'string'],
            'user_id'        => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'record_id'      => ['sometimes', 'nullable', 'integer'],
            'ip_address'     => ['sometimes', 'nullable', 'string', 'max:45'],
            'role'           => ['sometimes', 'nullable', 'string', 'in:' . implode(',', Role::values())],
            'date_from'      => ['sometimes', 'nullable', 'date'],
            'date_to'        => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'sort_by'        => ['sometimes', 'nullable', 'string', 'in:created_at,action,table_name,record_id,ip_address'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'page'           => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page'       => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
