<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'alert_number' => $this->alert_number,
            'alert_type'   => $this->alert_type,
            'severity'     => $this->severity,
            'description'  => $this->description,
            'status'       => $this->status,
            'resolved_at'  => $this->resolved_at?->format('Y-m-d H:i:s'),
            'created_at'   => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
            'assigned_to'  => UserResource::make($this->whenLoaded('assignedTo')),
            'alertable'    => $this->when(
                $this->relationLoaded('alertable') && $this->alertable !== null,
                fn () => [
                    'type' => class_basename($this->alertable),
                    'id'   => $this->alertable->id,
                ]
            ),
        ];
    }
}
