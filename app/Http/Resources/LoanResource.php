<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'loan_number'         => $this->loan_number,
            'loan_type'           => $this->loan_type,
            'principal_amount'    => (float) $this->principal_amount,
            'outstanding_balance' => (float) $this->outstanding_balance,
            'interest_rate'       => (float) $this->interest_rate,
            'term_months'         => (int) $this->term_months,
            'start_date'          => $this->start_date?->format('Y-m-d'),
            'end_date'            => $this->end_date?->format('Y-m-d'),
            'status'              => $this->status,
            'next_payment_date'   => $this->next_payment_date?->format('Y-m-d'),
            'customer'            => CustomerResource::make($this->whenLoaded('customer')),
            'created_at'          => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'          => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
