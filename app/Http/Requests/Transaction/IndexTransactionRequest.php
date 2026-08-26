<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class IndexTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_id'       => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'transaction_type' => ['sometimes', 'nullable', 'string', 'in:deposit,withdrawal,transfer,wire'],
            'type'             => ['sometimes', 'nullable', 'string', 'in:deposit,withdrawal,transfer,wire'],
            'status'           => ['sometimes', 'nullable', 'string', 'in:pending,completed,flagged,failed'],
            'channel'          => ['sometimes', 'nullable', 'string', 'in:branch,atm,online,mobile,wire'],
            'approved_by'      => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'date_from'        => ['sometimes', 'nullable', 'date'],
            'date_to'          => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'sort_by'          => ['sometimes', 'nullable', 'string', 'in:transaction_date,approved_at,amount,created_at'],
            'sort_direction'   => ['sometimes', 'nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'page'             => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page'         => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
