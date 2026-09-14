<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme'           => ['sometimes', 'required', 'string', 'in:dark,light,system'],
            'language'        => ['sometimes', 'required', 'string', 'in:en,fr,es,de,ar'],
            'dashboard_view'  => ['sometimes', 'required', 'string', 'in:default,compact,detailed'],
            'timezone'        => ['sometimes', 'required', 'string', 'timezone:all'],
            'date_format'     => ['sometimes', 'required', 'string', 'in:Y-m-d,d/m/Y,m/d/Y,d M Y'],
            'items_per_page'  => ['sometimes', 'required', 'integer', 'in:10,15,25,50,100'],
        ];
    }
}
