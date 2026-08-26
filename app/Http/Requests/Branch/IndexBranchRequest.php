<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;

class IndexBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'status'         => ['sometimes', 'nullable', 'string', 'in:active,inactive,under_renovation'],
            'manager_id'     => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'manager'        => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sort_by'        => ['sometimes', 'nullable', 'string', 'in:branch_name,branch_code,city,created_at'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'page'           => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page'       => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
