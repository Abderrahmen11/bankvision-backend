<?php

namespace App\Http\Requests\Settings;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! Hash::check((string) $value, (string) $this->user()?->password)) {
                        $fail('The current password is incorrect.');
                    }
                },
            ],
            'password'             => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
