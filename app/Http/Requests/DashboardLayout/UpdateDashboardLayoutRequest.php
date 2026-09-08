<?php

namespace App\Http\Requests\DashboardLayout;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'layout_data'                    => ['required', 'array'],
            'layout_data.widgets'            => ['required', 'array'],
            'layout_data.widgets.*.id'       => ['required', 'string'],
            'layout_data.widgets.*.type'     => ['required', 'string'],
            'layout_data.widgets.*.position' => ['required', 'array'],
            'layout_data.widgets.*.position.x' => ['required', 'numeric'],
            'layout_data.widgets.*.position.y' => ['required', 'numeric'],
            'layout_data.widgets.*.position.w' => ['required', 'numeric', 'min:1'],
            'layout_data.widgets.*.position.h' => ['required', 'numeric', 'min:1'],
            'layout_data.widgets.*.title'    => ['nullable', 'string'],
            'layout_data.widgets.*.visible'  => ['nullable', 'boolean'],
            'layout_data.widgets.*.settings' => ['nullable', 'array'],
            'layout_data.theme'              => ['nullable', 'string'],
            'layout_data.columns'            => ['nullable', 'numeric'],
        ];
    }
}
