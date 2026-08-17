<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'account_number' => $this->account_number,
            'account_type'   => $this->account_type,
            'currency'       => $this->currency,
            'balance'        => (float) $this->balance,
            'status'         => $this->status,
            'opened_date'    => $this->opened_date?->format('Y-m-d'),
            'interest_rate'  => (float) $this->interest_rate,
            'customer'       => CustomerResource::make($this->whenLoaded('customer')),
            'created_at'     => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'     => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
