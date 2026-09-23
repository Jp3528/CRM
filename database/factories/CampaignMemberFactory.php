<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignMember>
 */
class CampaignMemberFactory extends Factory
{
    protected $model = CampaignMember::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'member_type' => 'contact',
            'member_id' => Contact::factory(),
            'status' => 'pending',
            'source' => 'manual',
            'added_by' => User::factory(),
        ];
    }
}
