<?php

namespace App\Http\Requests;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof Campaign
            ? $this->user()?->can('update', $campaign) ?? false
            : $this->user()?->can('update', Campaign::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', Rule::in(Campaign::TYPES)],
            'status' => ['required', 'string', Rule::in(Campaign::STATUSES)],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'expected_revenue' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'actual_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $campaign = $this->route('campaign');

            if (! $campaign instanceof Campaign) {
                return;
            }

            $to = $this->input('status');

            if ($to && ! Campaign::canTransition($campaign->status, $to)) {
                $validator->errors()->add(
                    'status',
                    "Transición no permitida de {$campaign->status} a {$to}."
                );
            }
        });
    }
}
