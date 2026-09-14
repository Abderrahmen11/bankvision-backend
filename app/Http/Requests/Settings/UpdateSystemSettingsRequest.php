<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Bank profile
            'bank'          => ['sometimes', 'array'],
            'bank.name'     => ['sometimes', 'required', 'string', 'max:255'],
            'bank.legal_name' => ['sometimes', 'required', 'string', 'max:255'],
            'bank.address'  => ['sometimes', 'required', 'string', 'max:500'],
            'bank.city'     => ['sometimes', 'required', 'string', 'max:100'],
            'bank.country'  => ['sometimes', 'required', 'string', 'max:100'],
            'bank.phone'    => ['sometimes', 'required', 'string', 'max:50'],
            'bank.email'    => ['sometimes', 'required', 'email', 'max:255'],
            'bank.swift_code' => ['sometimes', 'required', 'string', 'max:20'],
            'bank.website'  => ['sometimes', 'required', 'string', 'max:255'],

            // Currency configuration
            'currency'        => ['sometimes', 'array'],
            'currency.code'   => ['sometimes', 'required', 'string', 'size:3', 'uppercase', Rule::in(['USD', 'EUR', 'GBP', 'TND', 'CHF', 'JPY'])],
            'currency.symbol' => ['sometimes', 'required', 'string', 'max:10'],

            // Interest rate configuration (annual percentage)
            'interest'                        => ['sometimes', 'array'],
            'interest.savings_rate'           => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'interest.checking_rate'          => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'interest.fixed_deposit_rate'     => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'interest.personal_loan_rate'     => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'interest.business_loan_rate'     => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'interest.mortgage_rate'          => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'interest.overdraft_rate'         => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'interest.late_payment_penalty'   => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
