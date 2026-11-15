<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Default settings dictionary representing today's hardcoded business values.
     */
    public static function defaults(): array
    {
        return [
            // General & Branding (Group 1)
            'general.system_name' => [
                'group' => 'general',
                'value' => 'Nigeria Civil Aviation Authority — DSD QA/IA System',
                'type' => 'string',
            ],
            'general.organisation_name' => [
                'group' => 'general',
                'value' => 'Nigeria Civil Aviation Authority',
                'type' => 'string',
            ],
            'general.logo_url' => [
                'group' => 'general',
                'value' => '/images/ncaa-logo.png',
                'type' => 'string',
            ],
            'general.primary_color' => [
                'group' => 'general',
                'value' => '#2a1770',
                'type' => 'string',
            ],
            'general.accent_color' => [
                'group' => 'general',
                'value' => '#008751',
                'type' => 'string',
            ],
            'general.support_email' => [
                'group' => 'general',
                'value' => 'qa.support@ncaa.gov.ng',
                'type' => 'string',
            ],
            'general.timezone' => [
                'group' => 'general',
                'value' => 'Africa/Lagos',
                'type' => 'string',
            ],
            'general.date_format' => [
                'group' => 'general',
                'value' => 'M d, Y',
                'type' => 'string',
            ],

            // Audit & CAP Policies (Pre-populated business rules)
            'audit.cap_due.level_1' => [
                'group' => 'audit',
                'value' => 7, // days
                'type' => 'integer',
            ],
            'audit.cap_due.level_2' => [
                'group' => 'audit',
                'value' => 14, // days
                'type' => 'integer',
            ],
            'audit.cap_due.level_3' => [
                'group' => 'audit',
                'value' => 30, // days
                'type' => 'integer',
            ],
            'audit.advance_reminder_days' => [
                'group' => 'audit',
                'value' => 14,
                'type' => 'integer',
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::defaults() as $key => $data) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'group' => $data['group'],
                    'value' => $data['value'],
                    'type' => $data['type'],
                ]
            );
        }

        app(SettingService::class)->clearCache();
    }
}
