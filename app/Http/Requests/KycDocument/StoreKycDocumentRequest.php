<?php

namespace App\Http\Requests\KycDocument;

use Illuminate\Foundation\Http\FormRequest;

class StoreKycDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type'   => ['required', 'string', 'max:50'],
            'document_number' => ['required', 'string', 'max:100'],
            'issuing_country' => ['nullable', 'string', 'max:100'],
            'expiry_date'     => ['nullable', 'date'],
            'file'            => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'attestation'     => ['required', 'accepted'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'     => 'An identification document file is required.',
            'file.mimes'        => 'The file must be an image or document of type: jpg, jpeg, png, pdf.',
            'file.max'          => 'The file size must not exceed 10 MB.',
            'attestation.accepted' => 'You must confirm the compliance attestation before submitting.',
        ];
    }
}
