<?php

namespace App\Http\Requests\Alert;

use Illuminate\Foundation\Http\FormRequest;

class IndexAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'severity'       => ['sometimes', 'nullable', 'string', 'in:low,medium,high'],
            'status'         => ['sometimes', 'nullable', 'string', 'in:open,in-progress,resolved'],
            'assigned_to'    => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'alert_type'     => ['sometimes', 'nullable', 'string'],
            'sort_by'        => ['sometimes', 'nullable', 'string', 'in:severity,status,created_at,resolved_at'],
            'sort_direction' => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'page'           => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page'       => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
