<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Setting::class) ?? false;
    }

    public function rules(): array
    {
        $rules = [];

        foreach (SettingService::CATALOG as $group) {
            foreach ($group as $key => $meta) {
                if ($this->has($key)) {
                    $rules[$key] = $meta['rules'];
                }
            }
        }

        return $rules;
    }
}
