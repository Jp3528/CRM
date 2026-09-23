<?php

namespace App\Http\Requests;

use App\Models\Campaign;
use App\Support\CampaignMemberType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof Campaign
            ? $this->user()?->can('update', $campaign) ?? false
            : $this->user()?->can('create', Campaign::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'member_type' => ['required', 'string', Rule::in(CampaignMemberType::keys())],
            'member_id' => ['required', 'integer', 'min:1'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
