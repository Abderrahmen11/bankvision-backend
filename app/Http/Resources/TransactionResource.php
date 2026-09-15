<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'transaction_number'     => $this->transaction_number,
            'account_id'             => $this->account_id,
            'destination_account_id' => $this->destination_account_id,
            'transaction_type'       => $this->transaction_type,
            'amount'                 => (string) $this->amount,
            'currency'               => $this->currency,
            'transaction_date'       => $this->transaction_date?->format('Y-m-d H:i:s'),
            'description'            => $this->description,
            'status'                 => $this->status,
            'channel'                => $this->channel,
            'counterparty'           => $this->counterparty,
            'approved_at'            => $this->approved_at?->format('Y-m-d H:i:s'),
            'account'                => AccountResource::make($this->whenLoaded('account')),
            'destination_account'    => AccountResource::make($this->whenLoaded('destinationAccount')),
            'approver'               => UserResource::make($this->whenLoaded('approver')),
            'created_at'             => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'             => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
