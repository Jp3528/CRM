<?php

namespace App\Http\Requests;

use App\Models\Communication;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $communication = $this->route('communication');

        return $communication instanceof Communication
            ? $this->user()?->can('update', $communication) ?? false
            : false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $communication = $this->route('communication');

            if ($communication instanceof Communication
                && ! in_array($communication->status, Communication::EDITABLE_STATUSES, true)) {
                $validator->errors()->add('body', 'Solo se pueden editar comunicaciones en borrador o en cola.');
            }
        });
    }
}
