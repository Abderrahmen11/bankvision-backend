<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'      => ['required', 'exists:customers,id'],
            'loan_type'        => ['required', 'in:mortgage,personal,auto,business'],
            'principal_amount' => ['required', 'numeric', 'min:1', 'max:999999999.99'],
            'interest_rate'    => ['required', 'numeric', 'min:0', 'max:100'],
            'term_months'      => ['required', 'integer', 'min:1', 'max:480'],
            'start_date'       => ['required', 'date'],
        ];
    }
}
