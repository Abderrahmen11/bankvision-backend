<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'customer_number'      => $this->customer_number,
            'full_name'            => $this->full_name,
            'email'                => $this->email,
            'phone'                => $this->phone,
            'address'              => $this->address,
            'city'                 => $this->city,
            'customer_type'        => $this->customer_type,
            'kyc_status'           => $this->kyc_status,
            'risk_level'           => $this->risk_level,
            'registration_date'    => $this->registration_date?->format('Y-m-d'),
            'branch'               => BranchResource::make($this->whenLoaded('branch')),
            'relationship_manager' => UserResource::make($this->whenLoaded('relationshipManager')),
            'accounts_count'       => $this->whenCounted('accounts'),
            'loans_count'          => $this->whenCounted('loans'),
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'           => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
