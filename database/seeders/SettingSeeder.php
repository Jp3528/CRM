<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Settings\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingService::CATALOG as $groupName => $group) {
            foreach ($group as $key => $meta) {
                Setting::firstOrCreate(
                    ['key' => $key],
                    [
                        'value' => (string) $meta['default'],
                        'type' => $meta['type'],
                        'group' => $groupName,
                        'is_public' => $meta['is_public'],
                        'description' => $meta['description'],
                    ]
                );
            }
        }

        SettingService::clearCache();
    }
}
