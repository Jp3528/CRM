<?php

namespace App\Http\Requests;

use App\Models\Communication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Communication::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'campaign_member_id' => ['nullable', 'integer', 'exists:campaign_members,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'channel' => ['required', 'string', Rule::in(Communication::CHANNELS)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $contactId = $this->input('contact_id');
            $leadId = $this->input('lead_id');

            if (empty($contactId) && empty($leadId)) {
                $validator->errors()->add('contact_id', 'Indica un contacto o un lead objetivo.');
            }

            if (! empty($contactId) && ! empty($leadId)) {
                $validator->errors()->add('lead_id', 'Indica solo un objetivo: contacto o lead, no ambos.');
            }
        });
    }
}
