<?php

namespace App\Http\Requests;

use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MessageTemplate::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', Rule::in(MessageTemplate::CHANNELS)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'status' => ['sometimes', 'string', Rule::in(MessageTemplate::STATUSES)],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
        ];
    }
}
