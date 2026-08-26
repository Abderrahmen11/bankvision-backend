<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

class IndexLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_id'             => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'loan_type'               => ['sometimes', 'nullable', 'string', 'in:personal,business,mortgage,auto'],
            'type'                    => ['sometimes', 'nullable', 'string', 'in:personal,business,mortgage,auto'],
            'status'                  => ['sometimes', 'nullable', 'string', 'in:pending,active,delinquent,defaulted,completed'],
            'term_months'             => ['sometimes', 'nullable', 'integer', 'min:1'],
            'principal_amount_min'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'principal_amount_max'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'outstanding_balance_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'outstanding_balance_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'interest_rate_min'       => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'interest_rate_max'       => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sort_by'                 => ['sometimes', 'nullable', 'string', 'in:principal_amount,outstanding_balance,interest_rate,start_date,end_date,next_payment_date,created_at'],
            'sort_direction'          => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'page'                    => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page'                => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
