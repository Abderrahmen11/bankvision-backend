<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'role'          => $this->role,
            'status'        => $this->status,
            'phone'         => $this->phone,
            'avatar'        => $this->avatar,
            'avatar_url'    => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'branch'        => BranchResource::make($this->whenLoaded('branch')),
            'created_at'    => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'    => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
