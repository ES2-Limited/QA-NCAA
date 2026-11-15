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

            // Audit Rules (Group 2)
            // 1. Finding levels: level id/key, name, cap_submission_days, closure_days
            'audit.finding_levels' => [
                'group' => 'audit',
                'value' => [
                    [
                        'level' => 1,
                        'name' => 'Level 1 — Critical / Safety Issue',
                        'cap_submission_days' => 3, // 72 hours / 3 days
                        'closure_days' => 14,
                    ],
                    [
                        'level' => 2,
                        'name' => 'Level 2 — Major Non-Conformance',
                        'cap_submission_days' => 14,
                        'closure_days' => 30,
                    ],
                    [
                        'level' => 3,
                        'name' => 'Level 3 — Minor Observation',
                        'cap_submission_days' => 30,
                        'closure_days' => 90,
                    ],
                ],
                'type' => 'json',
            ],

            // 2. Routine alert days & allowed frequencies
            'audit.routine_alert_days' => [
                'group' => 'audit',
                'value' => [14, 7],
                'type' => 'json',
            ],
            'audit.allowed_frequencies' => [
                'group' => 'audit',
                'value' => ['weekly', 'monthly', 'quarterly', 'bi-annual', 'annual'],
                'type' => 'json',
            ],

            // 3. Default question answer options
            'audit.default_answer_options' => [
                'group' => 'audit',
                'value' => ['Compliant', 'Non-compliant', 'N/A'],
                'type' => 'json',
            ],

            // 4. Answers that count as non-compliant (to flag / suggest NCRs)
            'audit.non_compliant_answers' => [
                'group' => 'audit',
                'value' => ['Non-compliant', 'non_compliant'],
                'type' => 'json',
            ],

            // 5. Reference numbering formats
            'audit.reference_format.ncr' => [
                'group' => 'audit',
                'value' => 'NCR-{YYYY}-{####}',
                'type' => 'string',
            ],
            'audit.reference_format.certificate' => [
                'group' => 'audit',
                'value' => 'DSD-QA-{YYYY}-{####}',
                'type' => 'string',
            ],
            'audit.reference_format.audit' => [
                'group' => 'audit',
                'value' => 'AUD-{YYYY}-{CODE}-{###}',
                'type' => 'string',
            ],

            // 6. Upload rules
            'audit.uploads.evidence' => [
                'group' => 'audit',
                'value' => [
                    'allowed_types' => 'jpg,jpeg,png,webp,pdf,docx,doc',
                    'max_size_kb' => 10240, // 10 MB
                ],
                'type' => 'json',
            ],
            'audit.uploads.documents' => [
                'group' => 'audit',
                'value' => [
                    'allowed_types' => 'pdf,docx',
                    'max_size_kb' => 20480, // 20 MB
                ],
                'type' => 'json',
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
