<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outstanding_balance' => ['sometimes', 'numeric', 'min:0'],
            'next_payment_date'   => ['sometimes', 'date'],
            'status'              => ['sometimes', 'in:pending,active,completed,defaulted,delinquent'],
        ];
    }
}
