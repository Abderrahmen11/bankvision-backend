<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_notifications'              => ['sometimes', 'boolean'],
            'push_notifications'               => ['sometimes', 'boolean'],
            'transaction_alerts'               => ['sometimes', 'boolean'],
            'loan_alerts'                      => ['sometimes', 'boolean'],
            'account_alerts'                   => ['sometimes', 'boolean'],
            'weekly_digest'                    => ['sometimes', 'boolean'],
            'alert_preferences'                => ['sometimes', 'array'],
            'alert_preferences.critical'       => ['sometimes', 'boolean'],
            'alert_preferences.high'           => ['sometimes', 'boolean'],
            'alert_preferences.medium'         => ['sometimes', 'boolean'],
            'alert_preferences.low'            => ['sometimes', 'boolean'],
        ];
    }
}
