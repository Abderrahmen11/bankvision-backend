<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'action'     => $this->action,
            'table_name' => $this->table_name,
            'record_id'  => $this->record_id,
            'old_values' => $this->when($this->old_values !== null, $this->old_values),
            'new_values' => $this->when($this->new_values !== null, $this->new_values),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'user'       => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
