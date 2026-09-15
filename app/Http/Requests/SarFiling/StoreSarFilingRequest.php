<?php

namespace App\Http\Requests\SarFiling;

use App\Enums\Role;
use App\Models\SarFiling;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSarFilingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, Role::flaggers(), true);
    }

    public function rules(): array
    {
        return [
            'customer_name'   => ['required', 'string', 'max:255'],
            'customer_number' => ['nullable', 'string', 'max:50'],
            'category'        => ['required', 'string', 'max:255', Rule::in(SarFiling::CATEGORIES)],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'status'          => ['sometimes', 'required', 'string', Rule::in(SarFiling::STATUSES)],
            'narrative'       => ['required', 'string', 'min:15', 'max:5000'],
            'action_taken'    => ['nullable', 'string', 'max:255'],
            'alert_id'        => ['nullable', 'integer', 'exists:alerts,id'],
        ];
    }
}
