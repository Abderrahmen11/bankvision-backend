<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
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
            'branch_code'     => $this->branch_code,
            'branch_name'     => $this->branch_name,
            'address'         => $this->address,
            'city'            => $this->city,
            'phone'           => $this->phone,
            'status'          => $this->status,
            'total_employees' => $this->total_employees,
            'manager'         => UserResource::make($this->whenLoaded('manager')),
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'      => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
