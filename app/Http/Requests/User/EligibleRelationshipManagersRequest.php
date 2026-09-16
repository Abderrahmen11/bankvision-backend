<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class EligibleRelationshipManagersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Role-based gate is enforced in the route middleware
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ];
    }
}
