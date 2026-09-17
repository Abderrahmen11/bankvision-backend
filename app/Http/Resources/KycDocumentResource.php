<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycDocumentResource extends JsonResource
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
            'customer_id'         => $this->customer_id,
            'document_type'       => $this->document_type,
            'document_number'     => $this->document_number,
            'issuing_country'     => $this->issuing_country,
            'expiry_date'         => $this->expiry_date?->format('Y-m-d'),
            'file_name'           => $this->file_name,
            'file_size'           => $this->file_size,
            'file_size_formatted' => $this->formatFileSize($this->file_size),
            'mime_type'           => $this->mime_type,
            'status'              => $this->status,
            'notes'               => $this->notes,
            'uploaded_by'         => $this->uploadedBy ? [
                'id'    => $this->uploadedBy->id,
                'name'  => $this->uploadedBy->name,
                'role'  => $this->uploadedBy->role,
                'email' => $this->uploadedBy->email,
            ] : null,
            'customer'            => $this->whenLoaded('customer', fn () => [
                'id'              => $this->customer->id,
                'customer_number' => $this->customer->customer_number,
                'full_name'       => $this->customer->full_name,
            ]),
            'uploaded_at'         => $this->uploaded_at?->format('Y-m-d H:i:s'),
            'verified_at'         => $this->verified_at?->format('Y-m-d H:i:s'),
            'created_at'          => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'          => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 2) . ' MB';
    }
}
