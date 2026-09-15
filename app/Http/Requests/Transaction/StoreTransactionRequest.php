<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id'       => ['required', 'exists:accounts,id'],
            'destination_account_id' => ['nullable', 'integer', 'exists:accounts,id', 'different:account_id', 'required_if:transaction_type,transfer'],
            'transaction_type' => ['required', 'in:deposit,withdrawal,transfer,wire'],
            'amount'           => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999.99'],
            'currency'         => ['sometimes', 'string', 'size:3'],
            'description'      => ['nullable', 'string'],
            'channel'          => ['sometimes', 'in:online,branch,atm,mobile'],
            'counterparty'     => ['nullable', 'string', 'max:255', 'required_if:transaction_type,wire'],
            'status'           => ['sometimes', 'in:completed,pending'],
        ];
    }
}

