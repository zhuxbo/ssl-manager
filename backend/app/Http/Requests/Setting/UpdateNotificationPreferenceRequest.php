<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];
        $preferences = config('notification.user_default_preferences', []);

        foreach ($preferences as $channel => $types) {
            $rules[$channel] = ['sometimes', 'array'];

            foreach ($types as $type => $default) {
                $rules[$channel.'.'.$type] = ['sometimes', 'boolean'];
            }
        }

        return $rules;
    }
}
