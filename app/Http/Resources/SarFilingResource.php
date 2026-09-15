<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SarFilingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'reference'       => $this->reference,
            'customer_name'   => $this->customer_name,
            'customer_number' => $this->customer_number,
            'category'        => $this->category,
            'amount'          => (string) $this->amount,
            'status'          => $this->status,
            'narrative'       => $this->narrative,
            'action_taken'    => $this->action_taken,
            'date'            => $this->created_at?->toDateString(),
            'filed_by'        => $this->whenLoaded('user', fn () => $this->user?->name),
            'alert_id'        => $this->alert_id,
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
