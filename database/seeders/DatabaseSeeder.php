<?php

namespace Database\Seeders;

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditApproval;
use App\Models\AuditResponse;
use App\Models\Certificate;
use App\Models\ChecklistQuestion;
use App\Models\CorrectiveActionPlan;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Document;
use App\Models\Finding;
use App\Models\Process;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AuditAssigned;
use App\Services\CertificateGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 0. Seed system business settings, workflow steps, lookups & roles/permissions
        $this->call(SettingSeeder::class);
        $this->call(WorkflowStepSeeder::class);
        $this->call(LookupSeeder::class);
        $this->call(RolePermissionSeeder::class);

        // 2. NCAA Organizational Hierarchy: Directorates -> Departments -> Units
        $directoratesData = [
            [
                'code' => 'DSD',
                'name' => 'Directorate of Special Duties',
                'head_of_directorate' => 'Director of Special Duties',
                'email' => 'dsd@ncaa.gov.ng',
                'description' => 'Oversees specialized audits, strategic quality assurance, presidential VIP flight protocols, and ministerial directives.',
                'departments' => [
                    [
                        'code' => 'DSD-QA',
                        'name' => 'Quality Assurance & Internal Audit Department',
                        'head_of_department' => 'Engr. Folake Adeleke',
                        'units' => [
                            ['code' => 'QA-AUD', 'name' => 'Internal Audit Execution Unit', 'head_of_unit' => 'Capt. Musa Bello'],
                            ['code' => 'QA-SURV', 'name' => 'Surveillance & Inspections Unit', 'head_of_unit' => 'Mr. Chidi Obi'],
                        ],
                    ],
                    [
                        'code' => 'DSD-VIP',
                        'name' => 'Special Flights & Presidential Protocols',
                        'head_of_department' => 'Mr. Usman Danladi',
                        'units' => [
                            ['code' => 'VIP-OPS', 'name' => 'VIP Flight Coordination Unit', 'head_of_unit' => 'Mrs. Halima Aliyu'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'DAWS',
                'name' => 'Directorate of Airworthiness Standards',
                'head_of_directorate' => 'Engr. K. O. Balogun',
                'email' => 'airworthiness@ncaa.gov.ng',
                'description' => 'Aircraft registration, airworthiness certification, maintenance organization approval, and continuing safety monitoring.',
                'departments' => [
                    [
                        'code' => 'DAWS-AIR',
                        'name' => 'Airworthiness Inspection & Certification',
                        'head_of_department' => 'Engr. B. Aliyu',
                        'units' => [
                            ['code' => 'AIR-AMO', 'name' => 'AMO Surveillance Unit', 'head_of_unit' => 'Engr. P. Okeke'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'DOLG',
                'name' => 'Directorate of Operations, Licensing and Training',
                'head_of_directorate' => 'Capt. S. A. Danbaba',
                'email' => 'operations@ncaa.gov.ng',
                'description' => 'AOC certification, flight crew licensing, recurrent simulator audits, and aerodrome operational compliance.',
                'departments' => [
                    [
                        'code' => 'DOLG-FLT',
                        'name' => 'Flight Operations Department',
                        'head_of_department' => 'Capt. T. Johnson',
                        'units' => [
                            ['code' => 'FLT-RAMP', 'name' => 'Ramp Safety & Surveillance Unit', 'head_of_unit' => 'Mr. Ibrahim Yakubu'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'DATR',
                'name' => 'Directorate of Air Transport Regulation',
                'head_of_directorate' => 'Mrs. Nneka Eze',
                'email' => 'datr@ncaa.gov.ng',
                'description' => 'Economic oversight of airlines, bilateral air service agreements (BASA), and commercial consumer protection.',
                'departments' => [],
            ],
            [
                'code' => 'DAAS',
                'name' => 'Directorate of Aerodrome & Airspace Standards',
                'head_of_directorate' => 'Engr. T. O. Okon',
                'email' => 'aerodrome@ncaa.gov.ng',
                'description' => 'Airport safety certification, runway lighting, fire fighting category compliance, and navigational aids.',
                'departments' => [],
            ],
        ];

        $createdDirectorates = [];
        $createdDepartments = [];
        $createdUnits = [];

        foreach ($directoratesData as $dirData) {
            $depts = $dirData['departments'] ?? [];
            unset($dirData['departments']);

            $directorate = Directorate::firstOrCreate(['code' => $dirData['code']], $dirData);
            $createdDirectorates[$directorate->code] = $directorate;

            foreach ($depts as $deptData) {
                $units = $deptData['units'] ?? [];
                unset($deptData['units']);
                $deptData['directorate_id'] = $directorate->id;

                $department = Department::firstOrCreate(['code' => $deptData['code']], $deptData);
                $createdDepartments[$department->code] = $department;

                foreach ($units as $unitData) {
                    $unitData['department_id'] = $department->id;
                    $unit = Unit::firstOrCreate(['code' => $unitData['code']], $unitData);
                    $createdUnits[$unit->code] = $unit;
                }
            }
        }

        // 3. Exactly One Demo User Per Role (password: "password")
        $usersData = [
            [
                'role' => 'system_admin',
                'name' => 'Capt. Musa Bello',
                'email' => 'admin@ncaa.gov.ng',
                'additional_emails' => ['system_admin@ncaa.gov.ng'],
                'staff_id' => 'NCAA/ADM/001',
                'phone' => '+234 803 123 4567',
                'directorate_code' => 'DSD',
                'department_code' => 'DSD-QA',
                'unit_code' => 'QA-AUD',
            ],
            [
                'role' => 'lead_auditor',
                'name' => 'Engr. Folake Adeleke',
                'email' => 'lead_auditor@ncaa.gov.ng',
                'staff_id' => 'NCAA/QA/042',
                'phone' => '+234 802 987 6543',
                'directorate_code' => 'DSD',
                'department_code' => 'DSD-QA',
                'unit_code' => 'QA-AUD',
            ],
            [
                'role' => 'field_auditor',
                'name' => 'Mr. Chidi Obi',
                'email' => 'auditor@ncaa.gov.ng',
                'additional_emails' => ['field_auditor@ncaa.gov.ng'],
                'staff_id' => 'NCAA/QA/077',
                'phone' => '+234 803 765 4321',
                'directorate_code' => 'DSD',
                'department_code' => 'DSD-QA',
                'unit_code' => 'QA-SURV',
            ],
            [
                'role' => 'reviewer',
                'name' => 'Mrs. Halima Aliyu',
                'email' => 'reviewer@ncaa.gov.ng',
                'staff_id' => 'NCAA/REV/015',
                'phone' => '+234 809 333 4455',
                'directorate_code' => 'DSD',
                'department_code' => 'DSD-QA',
                'unit_code' => 'QA-AUD',
            ],
            [
                'role' => 'head_of_audit',
                'name' => 'Dr. Emmanuel Okafor',
                'email' => 'head_of_audit@ncaa.gov.ng',
                'staff_id' => 'NCAA/DSD/005',
                'phone' => '+234 802 111 2233',
                'directorate_code' => 'DSD',
                'department_code' => 'DSD-QA',
                'unit_code' => 'QA-AUD',
            ],
            [
                'role' => 'director',
                'name' => 'Capt. S. A. Danbaba',
                'email' => 'director@ncaa.gov.ng',
                'staff_id' => 'NCAA/DIR/002',
                'phone' => '+234 805 777 8899',
                'directorate_code' => 'DSD',
                'department_code' => 'DSD-QA',
                'unit_code' => 'QA-AUD',
            ],
            [
                'role' => 'auditee',
                'name' => 'Mr. Ibrahim Yakubu',
                'email' => 'auditee@ncaa.gov.ng',
                'staff_id' => 'NCAA/DAWS/109',
                'phone' => '+234 805 555 1122',
                'directorate_code' => 'DAWS',
                'department_code' => 'DAWS-AIR',
                'unit_code' => 'AIR-AMO',
            ],
        ];

        $seededUsers = [];
        foreach ($usersData as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password'),
                    'role' => $u['role'],
                    'staff_id' => $u['staff_id'],
                    'phone' => $u['phone'],
                    'directorate_id' => $createdDirectorates[$u['directorate_code']]->id ?? null,
                    'department_id' => $createdDepartments[$u['department_code']]->id ?? null,
                    'unit_id' => $createdUnits[$u['unit_code']]->id ?? null,
                ]
            );

            $user->syncRoles([$u['role']]);
            $seededUsers[$u['role']] = $user;

            if (! empty($u['additional_emails'])) {
                foreach ($u['additional_emails'] as $aliasEmail) {
                    $aliasUser = User::updateOrCreate(
                        ['email' => $aliasEmail],
                        [
                            'name' => $u['name'],
                            'password' => Hash::make('password'),
                            'role' => $u['role'],
                            'staff_id' => $u['staff_id'],
                            'phone' => $u['phone'],
                            'directorate_id' => $createdDirectorates[$u['directorate_code']]->id ?? null,
                            'department_id' => $createdDepartments[$u['department_code']]->id ?? null,
                            'unit_id' => $createdUnits[$u['unit_code']]->id ?? null,
                        ]
                    );
                    $aliasUser->syncRoles([$u['role']]);
                }
            }
        }

        // 4. Document Library Sample Files & Seed Records (Seed before processes so documents can be linked)
        Storage::disk('local')->makeDirectory('documents');
        Storage::disk('public')->makeDirectory('documents');

        $sampleFiles = [
            [
                'title' => 'Internal QA Programme Requirements',
                'description' => 'Mandatory framework and operating procedures for NCAA Directorate internal quality audits.',
                'filename' => 'internal_qa_programme_requirements.pdf',
                'unit_code' => 'QA-AUD',
            ],
            [
                'title' => 'AMO Surveillance Checklist Guidelines',
                'description' => 'Inspection criteria and oversight protocols for Approved Maintenance Organizations under Nig.CARs Part 6.',
                'filename' => 'amo_surveillance_checklist_guidelines.pdf',
                'unit_code' => 'AIR-AMO',
            ],
        ];

        foreach ($sampleFiles as $sample) {
            $sourcePath = database_path('seeders/files/'.$sample['filename']);
            $targetStoragePath = 'documents/'.$sample['filename'];

            if (File::exists($sourcePath)) {
                $fileBytes = File::get($sourcePath);
                Storage::disk('local')->put($targetStoragePath, $fileBytes);
                Storage::disk('public')->put($targetStoragePath, $fileBytes);
                $size = File::size($sourcePath);
                $mimeType = 'application/pdf';

                Document::firstOrCreate(
                    ['title' => $sample['title']],
                    [
                        'description' => $sample['description'],
                        'file_path' => $targetStoragePath,
                        'original_name' => $sample['filename'],
                        'mime_type' => $mimeType,
                        'size' => $size,
                        'unit_id' => $createdUnits[$sample['unit_code']]->id ?? null,
                        'uploaded_by' => $seededUsers['lead_auditor']->id,
                    ]
                );
            }
        }

        // 5. Processes & Checklist Questions (Replaces old Checklists)
        // Process 1: AMO Internal QA Audit (DAWS, routine, monthly, next due in 10 days)
        $procAmo = Process::firstOrCreate(
            ['name' => 'AMO Internal QA Audit'],
            [
                'description' => 'Comprehensive continuing airworthiness & maintenance organization quality inspection template.',
                'directorate_id' => $createdDirectorates['DAWS']->id,
                'department_id' => $createdDepartments['DAWS-AIR']->id ?? null,
                'unit_id' => $createdUnits['AIR-AMO']->id ?? null,
                'type' => 'routine',
                'frequency' => 'monthly',
                'next_due_at' => now()->addDays(10),
                'created_by' => $seededUsers['lead_auditor']->id,
                'status' => 'active',
            ]
        );

        // Link to AMO Surveillance Checklist Guidelines document
        $amoDoc = Document::where('title', 'AMO Surveillance Checklist Guidelines')->first();
        if ($amoDoc) {
            $procAmo->documents()->syncWithoutDetaching([$amoDoc->id]);
        }

        $amoQuestions = [
            [
                'question' => 'Are all aircraft technical logbooks certified by authorized type-rated engineers with valid NCAA AME licenses?',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 5.4.1',
                'guidance' => 'Inspect tech logs for the past 30 days and sample 5 defect rectifications.',
                'source' => 'manual',
            ],
            [
                'question' => 'Does the precision tool store hold current ISO 17025 calibration records for all torque and avionics testers?',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 6.5.2',
                'guidance' => 'Verify calibration expiration stickers and quarantine shelves for out-of-spec tooling.',
                'source' => 'manual',
            ],
            [
                'question' => 'Confirm storage of aircraft composites and bonded sealants within specified temperature and shelf-life thresholds.',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 6.3.4 & ICAO Annex 8',
                'guidance' => 'Inspect climate logging monitors in bonded storage.',
                'source' => 'manual',
            ],
            [
                'question' => 'Are all rotable parts tagged with valid Airworthiness Release Certificates (NCAA Form One / 8130-3 / EASA Form 1)?',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 5.6.1',
                'guidance' => 'Random physical bin check of 10 incoming rotable parts.',
                'source' => 'manual',
            ],
            [
                'question' => 'Select all hangar fire suppression and emergency safety equipment verified functional during physical walkthrough.',
                'type' => 'checkbox',
                'options' => ['Foam deluge system', 'CO2 wheel extinguishers', 'Eye wash stations', 'Emergency egress lighting'],
                'requirement_ref' => 'Nig.CARs Part 6.4.2',
                'guidance' => 'Ensure inspection tags are dated within the past 12 months.',
                'source' => 'manual',
            ],
            [
                'question' => 'Describe any open structural repairs currently deferred beyond OEM structural repair manual (SRM) allowances.',
                'type' => 'text',
                'options' => null,
                'requirement_ref' => 'Nig.CARs Part 5.5.3',
                'guidance' => 'Record tail registration, date deferred, and engineering authority authorization.',
                'source' => 'manual',
            ],
            [
                'question' => 'Has the internal QA independent audit schedule achieved 100% completion for all active hangar bays this quarter?',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'ICAO Doc 9859 Safety Management Systems',
                'guidance' => 'Review internal audit closure log and directorate corrective actions.',
                'source' => 'manual',
            ],
            [
                'question' => 'Verify that maintenance personnel recurrent training on Human Factors and Nig.CARs updates is within 24 months.',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 6.2.2',
                'guidance' => 'Check training matrix for all active shift engineers.',
                'source' => 'manual',
            ],
        ];

        foreach ($amoQuestions as $idx => $q) {
            ChecklistQuestion::firstOrCreate(
                ['process_id' => $procAmo->id, 'order' => $idx + 1],
                array_merge($q, ['order' => $idx + 1])
            );
        }

        // Process 2: Ramp Safety Spot Check (unplanned, 5 questions)
        $procRamp = Process::firstOrCreate(
            ['name' => 'Ramp Safety Spot Check'],
            [
                'description' => 'Unannounced ramp surveillance inspection protocol evaluating tarmac safety and ground handling compliance.',
                'directorate_id' => $createdDirectorates['DOLG']->id,
                'department_id' => $createdDepartments['DOLG-FLT']->id ?? null,
                'unit_id' => $createdUnits['FLT-RAMP']->id ?? null,
                'type' => 'unplanned',
                'frequency' => null,
                'next_due_at' => null,
                'created_by' => $seededUsers['lead_auditor']->id,
                'status' => 'active',
            ]
        );

        $rampQuestions = [
            [
                'question' => 'Are all Ground Support Equipment (GSE) active on ramp displaying valid NCAA annual safety inspection passes?',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 8.4.1.2 & ICAO Doc 10121',
                'guidance' => 'Inspect pushback tugs, belt loaders, and fuel bowsers.',
                'source' => 'manual',
            ],
            [
                'question' => 'Confirm Foreign Object Debris (FOD) mitigation sweep completed and signed in the gate log prior to aircraft docking.',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'ICAO Doc 9137 Part 2 & Nig.CARs Part 12',
                'guidance' => 'Inspect ramp bin cleanliness within 15 meters of aircraft parking.',
                'source' => 'manual',
            ],
            [
                'question' => 'Select verified PPE worn by all marshaling and ramp operations personnel on active aircraft stands.',
                'type' => 'checkbox',
                'options' => ['High-visibility vest', 'Noise-attenuating earmuffs', 'Steel-toe footwear'],
                'requirement_ref' => 'Nig.CARs Part 8.5.3',
                'guidance' => 'Full personal protective equipment is mandatory within active safety lines.',
                'source' => 'manual',
            ],
            [
                'question' => 'Verify aircraft fuel hydrant bonding and earthing cable continuity before fuel transfer commences.',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 8.8.2.1',
                'guidance' => 'Inspect static grounding reel wire integrity.',
                'source' => 'manual',
            ],
            [
                'question' => 'Record any observed safety anomalies, unauthorized vehicle speeding, or jet blast boundary violations.',
                'type' => 'text',
                'options' => null,
                'requirement_ref' => 'Nig.CARs Part 8.4.5',
                'guidance' => 'Note gate number, GSE equipment ID, and airline flight code.',
                'source' => 'manual',
            ],
        ];

        foreach ($rampQuestions as $idx => $q) {
            ChecklistQuestion::firstOrCreate(
                ['process_id' => $procRamp->id, 'order' => $idx + 1],
                array_merge($q, ['order' => $idx + 1])
            );
        }

        // 6. Audit Engagements (Prompt 5 Seeder Requirements)
        // Audit 1: in_progress audit of "Ramp Safety Spot Check"
        $rampSnapshot = $procRamp->questions->map(function ($q) {
            return [
                'id' => $q->id,
                'order' => $q->order,
                'question' => $q->question,
                'type' => $q->type,
                'options' => $q->options,
                'requirement_ref' => $q->requirement_ref,
                'guidance' => $q->guidance,
                'source' => $q->source,
            ];
        })->toArray();

        $audit1 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-001'],
            [
                'title' => 'Ramp Safety Spot Check — Active Ramp Surveillance',
                'type' => 'unplanned',
                'process_id' => $procRamp->id,
                'checklist_id' => $procRamp->id,
                'directorate_id' => $createdDirectorates['DOLG']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Abuja Airport (DNAA / ABV)',
                'start_date' => now()->subDays(2),
                'scheduled_for' => now()->subDays(2),
                'status' => 'in_progress',
                'questions_snapshot' => $rampSnapshot,
                'scope' => 'Concourse B Apron, Hydrant Fueling Pit 4, Baggage Make-up Area, and GSE Maintenance Workshop.',
                'executive_summary' => 'Routine surprise surveillance conducted jointly by NCAA DSD and Flight Safety inspectors.',
                'ai_executive_summary' => "### Executive Audit Summary (NCAA Directorate of Special Duties)\n**Engagement:** Ramp Safety & Fuel Quality Surveillance Audit - Abuja\n**Audited Directorate/Station:** Directorate of Operations, Licensing and Training (DOLG)\n**Overall Quality Compliance Index:** 80%\n\n#### 1. Strategic Compliance Assessment\nThe DSD Internal Audit team concluded surveillance inspection at Abuja International Airport. Overall adherence meets basic NCAA criteria.\n\n#### 2. Summary of Findings\n- **Major Operational Findings:** 1 (GSE uncalibrated fuel hydrant static line)\n- **Minor Discrepancies:** 1 (FOD bin documentation delay)",
            ]
        );

        $audit1->auditors()->syncWithoutDetaching([$seededUsers['field_auditor']->id]);

        // Audit 2: Scheduled audit of "AMO Internal QA Audit" (next week, field_auditor + reviewer assigned, notifications created)
        $amoSnapshot = $procAmo->questions->map(function ($q) {
            return [
                'id' => $q->id,
                'order' => $q->order,
                'question' => $q->question,
                'type' => $q->type,
                'options' => $q->options,
                'requirement_ref' => $q->requirement_ref,
                'guidance' => $q->guidance,
                'source' => $q->source,
            ];
        })->toArray();

        $audit2 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-002'],
            [
                'title' => 'AMO Internal QA Audit — Next Week Routine Inspection',
                'type' => 'routine',
                'process_id' => $procAmo->id,
                'checklist_id' => $procAmo->id,
                'directorate_id' => $createdDirectorates['DAWS']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Lagos MMA Maintenance Hangar 2',
                'start_date' => now()->addWeek(),
                'scheduled_for' => now()->addWeek(),
                'status' => 'scheduled',
                'questions_snapshot' => $amoSnapshot,
                'scope' => 'Hangar Line Maintenance, Tool Calibration Store, Rotable Parts Bonded Stores, and Nig.CARs Part 6 compliance.',
            ]
        );

        $audit2->auditors()->syncWithoutDetaching([$seededUsers['field_auditor']->id]);

        // Create Notifications for Audit 2
        $seededUsers['field_auditor']->notify(new AuditAssigned($audit2));
        $seededUsers['reviewer']->notify(new AuditAssigned($audit2));

        // Prompt 6: Seed 3 AuditAnswers for in_progress audit1 with Abuja Airport coordinates & sample photos
        $abujaLat = 9.0067920;
        $abujaLng = 7.2631720;

        // Ensure evidence directory exists
        Storage::disk('local')->makeDirectory('evidence');
        Storage::disk('public')->makeDirectory('evidence');

        // Answer 1 (Question Key 0)
        $ans1 = AuditAnswer::firstOrCreate(
            ['audit_id' => $audit1->id, 'question_key' => 0],
            [
                'value' => 'Compliant',
                'comment' => 'Pushback tugs and belt loaders display active NCAA mechanical passes expiring Dec 2026.',
                'lat' => $abujaLat,
                'lng' => $abujaLng,
                'location_accuracy' => 4.5,
                'answered_at' => now()->subHours(4),
                'answered_by' => $seededUsers['field_auditor']->id,
            ]
        );

        $ans1->attachments()->firstOrCreate(
            ['path' => 'evidence/ramp_sample_photo1.png'],
            [
                'original_name' => 'gse_inspection_pass.png',
                'mime_type' => 'image/png',
                'size' => 10240,
                'uploaded_by' => $seededUsers['field_auditor']->id,
            ]
        );

        // Answer 2 (Question Key 1)
        $ans2 = AuditAnswer::firstOrCreate(
            ['audit_id' => $audit1->id, 'question_key' => 1],
            [
                'value' => 'Compliant',
                'comment' => 'FOD bin inspection log signed off for gate 4 prior to docking.',
                'lat' => $abujaLat,
                'lng' => $abujaLng,
                'location_accuracy' => 5.2,
                'answered_at' => now()->subHours(3),
                'answered_by' => $seededUsers['field_auditor']->id,
            ]
        );

        $ans2->attachments()->firstOrCreate(
            ['path' => 'evidence/ramp_sample_photo2.png'],
            [
                'original_name' => 'fod_sweep_gate4.png',
                'mime_type' => 'image/png',
                'size' => 10240,
                'uploaded_by' => $seededUsers['field_auditor']->id,
            ]
        );

        // Answer 3 (Question Key 2 - checkbox)
        AuditAnswer::firstOrCreate(
            ['audit_id' => $audit1->id, 'question_key' => 2],
            [
                'value' => ['High-visibility vest', 'Noise-attenuating earmuffs', 'Steel-toe footwear'],
                'comment' => '100% PPE compliance observed across line marshals on gate 4 stand.',
                'lat' => $abujaLat,
                'lng' => $abujaLng,
                'location_accuracy' => 3.8,
                'answered_at' => now()->subHours(2),
                'answered_by' => $seededUsers['field_auditor']->id,
            ]
        );

        foreach ($procRamp->questions as $it) {
            $status = ($it->order === 4) ? 'non_compliant' : 'compliant';
            $notes = ($it->order === 4) ? 'Static bonding cable showed physical outer insulation wear and missing continuity tag.' : 'Verified compliant during physical ramp inspection.';

            AuditResponse::firstOrCreate(
                ['audit_id' => $audit1->id, 'checklist_item_id' => $it->id],
                [
                    'status' => $status,
                    'notes' => $notes,
                    'auditor_id' => $seededUsers['field_auditor']->id,
                ]
            );
        }

        $fnd1 = Finding::firstOrCreate(
            ['finding_number' => 'FND-2026-DSD-01'],
            [
                'audit_id' => $audit1->id,
                'title' => 'Compromised Static Bonding Cable on Aviation Fuel Bowser #3',
                'severity' => 'major',
                'condition' => 'Fuel bowser #3 servicing morning commercial departures had a fraying static bonding line with zero continuity record tag attached.',
                'criteria' => 'Nig.CARs Part 8.8.2.1 and ICAO Doc 9137 Safety Standards.',
                'cause' => 'Preventive maintenance schedule for ground refuelling gear exceeded by 45 days without mandatory re-certification.',
                'effect' => 'Heightened electro-static spark hazard during pressurized jet fuel transfer into aircraft wing tanks.',
                'recommendation' => 'Immediately quarantine Bowser #3 until bonding cable replacement and resistance continuity test certification by NCAA inspectors.',
                'status' => 'cap_submitted',
                'due_date' => now()->addDays(7),
                'ai_analysis' => [
                    'severity_recommendation' => 'major',
                    'risk_level' => 'High Electrostatic Hazard during Fueling',
                    'root_cause_analysis' => 'Deferred ramp equipment maintenance intervals and lack of daily pre-service spark-arrestor checks.',
                    'regulatory_citations' => 'Nig.CARs Part 8.8.2.1, NFPA 407 Aircraft Fuel Servicing',
                ],
            ]
        );

        // 7. Prompt 7: Setup Audits in specific lifecycle states
        // Audit 3: Fully through the chain (director_approved) with 1 closed NCR and an issued certificate
        $audit3 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-003'],
            [
                'title' => 'AMO Internal QA Audit — Q1 Certified Hangar Surveillance',
                'type' => 'routine',
                'process_id' => $procAmo->id,
                'checklist_id' => $procAmo->id,
                'directorate_id' => $createdDirectorates['DAWS']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Lagos MMA Maintenance Hangar 1',
                'start_date' => now()->subDays(14),
                'end_date' => now()->subDays(2),
                'scheduled_for' => now()->subDays(14),
                'status' => 'director_approved',
                'submitted_at' => now()->subDays(10),
                'questions_snapshot' => $amoSnapshot,
                'scope' => 'Hangar tool store, calibration logs, Nig.CARs Part 6 compliance.',
                'ai_summary' => "The NCAA Directorate of Special Duties completed an internal QA audit of the Lagos Maintenance Hangar. Conformance met high regulatory standards at 88% overall compliance.\n\nOne Level 2 NCR was raised regarding calibration sticker expiry on a pneumatic rivet gun and immediately resolved via re-calibration.\n\nAll corrective actions were verified compliant and statutory certification has been authorized by the Director of Special Duties.",
            ]
        );

        $audit3->auditors()->syncWithoutDetaching([$seededUsers['field_auditor']->id]);

        // Approval Trail for Audit 3
        AuditApproval::firstOrCreate(
            ['audit_id' => $audit3->id, 'action' => 'reviewed'],
            [
                'user_id' => $seededUsers['reviewer']->id,
                'role' => 'reviewer',
                'comment' => 'Field verification satisfactory. CAP verified and closed.',
                'created_at' => now()->subDays(8),
            ]
        );

        AuditApproval::firstOrCreate(
            ['audit_id' => $audit3->id, 'action' => 'head_approved'],
            [
                'user_id' => $seededUsers['head_of_audit']->id,
                'role' => 'head_of_audit',
                'comment' => 'Endorsed for Director authorization.',
                'created_at' => now()->subDays(5),
            ]
        );

        AuditApproval::firstOrCreate(
            ['audit_id' => $audit3->id, 'action' => 'director_approved'],
            [
                'user_id' => $seededUsers['director']->id,
                'role' => 'director',
                'comment' => 'Quality certification authorized.',
                'created_at' => now()->subDays(2),
            ]
        );

        // 1 Closed NCR for Audit 3
        $ncrClosed = Finding::firstOrCreate(
            ['reference' => 'NCR-2026-0001'],
            [
                'audit_id' => $audit3->id,
                'finding_number' => 'NCR-2026-0001',
                'title' => 'Pneumatic Tool Calibration Sticker Overdue',
                'level' => 2,
                'description' => 'Pneumatic rivet gun sn# 4022 in hangar bay 1 tool store displayed calibration expiry date 10 days past interval.',
                'condition' => 'Pneumatic rivet gun sn# 4022 in hangar bay 1 tool store displayed calibration expiry date 10 days past interval.',
                'requirement_ref' => 'Nig.CARs Part 6.5.2',
                'criteria' => 'Nig.CARs Part 6.5.2',
                'assigned_unit_id' => $createdUnits['AIR-AMO']->id ?? null,
                'raised_by' => $seededUsers['reviewer']->id,
                'cap_due_at' => now()->subDays(3),
                'due_date' => now()->subDays(3)->toDateString(),
                'status' => 'closed',
            ]
        );

        $capClosed = CorrectiveActionPlan::firstOrCreate(
            ['ncr_id' => $ncrClosed->id],
            [
                'finding_id' => $ncrClosed->id,
                'description' => 'Tool immediately quarantined, re-calibrated at NCAA approved laboratory, and tool room inventory audit completed.',
                'proposed_action' => 'Tool immediately quarantined and re-calibrated.',
                'root_cause' => 'Quarterly tool tracking tag fell off during tool room cleaning.',
                'root_cause_analysis' => 'Quarterly tool tracking tag fell off.',
                'target_date' => now()->subDays(4),
                'target_completion_date' => now()->subDays(4),
                'submitted_by' => $seededUsers['auditee']->id,
                'responsible_person' => $seededUsers['auditee']->name,
                'status' => 'accepted',
                'reviewer_comment' => 'Calibration certificate #ISO-9921 inspected and verified compliant.',
                'reviewed_by' => $seededUsers['reviewer']->id,
                'reviewed_at' => now()->subDays(4),
            ]
        );

        // Issue Certificate for Audit 3
        app(CertificateGenerator::class)->generate($audit3, $seededUsers['director']->id);

        // Audit 4: At "reviewed" waiting for Head of Audit
        $audit4 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-004'],
            [
                'title' => 'Ramp Operations Surveillance — Awaiting Head Approval',
                'type' => 'routine',
                'process_id' => $procRamp->id,
                'checklist_id' => $procRamp->id,
                'directorate_id' => $createdDirectorates['DOLG']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Abuja Airport (DNAA / ABV)',
                'start_date' => now()->subDays(4),
                'scheduled_for' => now()->subDays(4),
                'status' => 'reviewed',
                'submitted_at' => now()->subDays(2),
                'questions_snapshot' => $rampSnapshot,
                'scope' => 'Abuja Apron Gate 1-5 surveillance and tarmac speed compliance.',
            ]
        );

        AuditApproval::firstOrCreate(
            ['audit_id' => $audit4->id, 'action' => 'reviewed'],
            [
                'user_id' => $seededUsers['reviewer']->id,
                'role' => 'reviewer',
                'comment' => 'Technical evaluation complete. Recommended for Head of Audit endorsement.',
                'created_at' => now()->subDay(),
            ]
        );

        // Open NCR with overdue CAP for auditee unit
        $ncrOverdue = Finding::firstOrCreate(
            ['reference' => 'NCR-2026-0002'],
            [
                'audit_id' => $audit4->id,
                'finding_number' => 'NCR-2026-0002',
                'title' => 'Missing Apron Fire Extinguisher Monthly Inspection Tag',
                'level' => 1,
                'description' => 'Wheel-mounted 50kg dry powder fire extinguisher on stand 3 missing statutory monthly inspection sign-off tag.',
                'condition' => 'Wheel-mounted 50kg dry powder fire extinguisher on stand 3 missing statutory monthly inspection sign-off tag.',
                'requirement_ref' => 'Nig.CARs Part 8.5.3 & ICAO Doc 9137',
                'criteria' => 'Nig.CARs Part 8.5.3',
                'assigned_unit_id' => $createdUnits['FLT-RAMP']->id ?? null,
                'raised_by' => $seededUsers['reviewer']->id,
                'cap_due_at' => now()->subHours(24), // 24 hours overdue
                'due_date' => now()->subDays(1)->toDateString(),
                'status' => 'open',
            ]
        );

        // 8. Prompt 8 Requirements: Additional Processes, Audits across 6 months, NCRs & Certificates
        // Processes 3 - 6 across DSD, DAWS, DOLG (Total >= 6 processes across 3 directorates)
        $procDg = Process::firstOrCreate(
            ['name' => 'Dangerous Goods Ground Handling Audit'],
            [
                'description' => 'Surveillance of cargo terminal hazmat storage, segregation, and acceptance documentation under Nig.CARs Part 9.',
                'directorate_id' => $createdDirectorates['DOLG']->id,
                'department_id' => $createdDepartments['DOLG-FLT']->id ?? null,
                'unit_id' => $createdUnits['FLT-RAMP']->id ?? null,
                'type' => 'routine',
                'frequency' => 'quarterly',
                'next_due_at' => now()->addDays(20),
                'created_by' => $seededUsers['lead_auditor']->id,
                'status' => 'active',
            ]
        );

        $procAvionics = Process::firstOrCreate(
            ['name' => 'Avionics Workshop Calibration Review'],
            [
                'description' => 'Precision measurement tool verification, laboratory traceability, and environmental controls in component shops.',
                'directorate_id' => $createdDirectorates['DAWS']->id,
                'department_id' => $createdDepartments['DAWS-AIR']->id ?? null,
                'unit_id' => $createdUnits['AIR-AMO']->id ?? null,
                'type' => 'routine',
                'frequency' => 'bi-annual',
                'next_due_at' => now()->addDays(14),
                'created_by' => $seededUsers['lead_auditor']->id,
                'status' => 'active',
            ]
        );

        $procVip = Process::firstOrCreate(
            ['name' => 'VIP Flight Operation Security & Quality Audit'],
            [
                'description' => 'Executive presidential fleet passenger screening, manifest handling, and ramp escort surveillance protocols.',
                'directorate_id' => $createdDirectorates['DSD']->id,
                'department_id' => $createdDepartments['DSD-VIP']->id ?? null,
                'unit_id' => $createdUnits['VIP-OPS']->id ?? null,
                'type' => 'unplanned',
                'frequency' => null,
                'next_due_at' => null,
                'created_by' => $seededUsers['lead_auditor']->id,
                'status' => 'active',
            ]
        );

        $procQms = Process::firstOrCreate(
            ['name' => 'Quality Management System Compliance Review'],
            [
                'description' => 'Internal evaluation of DSD audit procedures, inspector competence, closing records, and statutory ICAO USOAP readiness.',
                'directorate_id' => $createdDirectorates['DSD']->id,
                'department_id' => $createdDepartments['DSD-QA']->id ?? null,
                'unit_id' => $createdUnits['QA-AUD']->id ?? null,
                'type' => 'routine',
                'frequency' => 'annual',
                'next_due_at' => now()->addDays(5),
                'created_by' => $seededUsers['lead_auditor']->id,
                'status' => 'active',
            ]
        );

        // Additional Audits across the last 6 months covering all statuses (Total >= 10 audits)
        // Audit 5: 5 months ago (director_approved, DAWS, completed with certificate)
        $audit5Date = now()->subMonths(5)->startOfMonth()->addDays(10);
        $audit5 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-005'],
            [
                'title' => 'Avionics Workshop Calibration Review — Mid-Year Audit',
                'type' => 'routine',
                'process_id' => $procAvionics->id,
                'checklist_id' => $procAvionics->id,
                'directorate_id' => $createdDirectorates['DAWS']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Lagos MMA Component Shop',
                'start_date' => $audit5Date,
                'end_date' => $audit5Date->copy()->addDays(3),
                'scheduled_for' => $audit5Date,
                'status' => 'director_approved',
                'created_at' => $audit5Date,
                'updated_at' => $audit5Date->copy()->addDays(5),
            ]
        );
        AuditApproval::firstOrCreate(['audit_id' => $audit5->id, 'action' => 'director_approved'], [
            'user_id' => $seededUsers['director']->id,
            'role' => 'director',
            'comment' => 'Certified and approved.',
            'created_at' => $audit5Date->copy()->addDays(5),
        ]);
        app(CertificateGenerator::class)->generate($audit5, $seededUsers['director']->id);

        // Audit 6: 4 months ago (director_approved, DOLG, completed with certificate)
        $audit6Date = now()->subMonths(4)->startOfMonth()->addDays(12);
        $audit6 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-006'],
            [
                'title' => 'Dangerous Goods Ground Handling — Kano Airport Surveillance',
                'type' => 'routine',
                'process_id' => $procDg->id,
                'checklist_id' => $procDg->id,
                'directorate_id' => $createdDirectorates['DOLG']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Mallam Aminu Kano Airport (DNKN / KAN)',
                'start_date' => $audit6Date,
                'end_date' => $audit6Date->copy()->addDays(2),
                'scheduled_for' => $audit6Date,
                'status' => 'director_approved',
                'created_at' => $audit6Date,
                'updated_at' => $audit6Date->copy()->addDays(4),
            ]
        );
        AuditApproval::firstOrCreate(['audit_id' => $audit6->id, 'action' => 'director_approved'], [
            'user_id' => $seededUsers['director']->id,
            'role' => 'director',
            'comment' => 'Certified and approved.',
            'created_at' => $audit6Date->copy()->addDays(4),
        ]);
        app(CertificateGenerator::class)->generate($audit6, $seededUsers['director']->id);

        // Audit 7: 3 months ago (director_approved, DSD)
        $audit7Date = now()->subMonths(3)->startOfMonth()->addDays(8);
        $audit7 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-007'],
            [
                'title' => 'VIP Flight Operation Security Audit — Abuja Presidential Wing',
                'type' => 'unplanned',
                'process_id' => $procVip->id,
                'checklist_id' => $procVip->id,
                'directorate_id' => $createdDirectorates['DSD']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Presidential Air Fleet Wing, Abuja',
                'start_date' => $audit7Date,
                'end_date' => $audit7Date->copy()->addDays(1),
                'scheduled_for' => $audit7Date,
                'status' => 'director_approved',
                'created_at' => $audit7Date,
                'updated_at' => $audit7Date->copy()->addDays(3),
            ]
        );
        AuditApproval::firstOrCreate(['audit_id' => $audit7->id, 'action' => 'director_approved'], [
            'user_id' => $seededUsers['director']->id,
            'role' => 'director',
            'comment' => 'Security protocol verified compliant.',
            'created_at' => $audit7Date->copy()->addDays(3),
        ]);

        // Audit 8: 2 months ago (director_approved, DAWS)
        $audit8Date = now()->subMonths(2)->startOfMonth()->addDays(15);
        $audit8 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-008'],
            [
                'title' => 'AMO Quality Management Follow-up Inspection',
                'type' => 'routine',
                'process_id' => $procAmo->id,
                'checklist_id' => $procAmo->id,
                'directorate_id' => $createdDirectorates['DAWS']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Port Harcourt Hangar',
                'start_date' => $audit8Date,
                'end_date' => $audit8Date->copy()->addDays(2),
                'scheduled_for' => $audit8Date,
                'status' => 'director_approved',
                'created_at' => $audit8Date,
                'updated_at' => $audit8Date->copy()->addDays(4),
            ]
        );
        AuditApproval::firstOrCreate(['audit_id' => $audit8->id, 'action' => 'director_approved'], [
            'user_id' => $seededUsers['director']->id,
            'role' => 'director',
            'comment' => 'Certified.',
            'created_at' => $audit8Date->copy()->addDays(4),
        ]);

        // Audit 9: 1 month ago (director_approved, DOLG)
        $audit9Date = now()->subMonths(1)->startOfMonth()->addDays(18);
        $audit9 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-009'],
            [
                'title' => 'Dangerous Goods Acceptance & Cargo Staging Surveillance',
                'type' => 'routine',
                'process_id' => $procDg->id,
                'checklist_id' => $procDg->id,
                'directorate_id' => $createdDirectorates['DOLG']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Lagos Cargo Terminal 2',
                'start_date' => $audit9Date,
                'end_date' => $audit9Date->copy()->addDays(3),
                'scheduled_for' => $audit9Date,
                'status' => 'director_approved',
                'created_at' => $audit9Date,
                'updated_at' => $audit9Date->copy()->addDays(5),
            ]
        );
        AuditApproval::firstOrCreate(['audit_id' => $audit9->id, 'action' => 'director_approved'], [
            'user_id' => $seededUsers['director']->id,
            'role' => 'director',
            'comment' => 'Approved.',
            'created_at' => $audit9Date->copy()->addDays(5),
        ]);

        // Audit 10: This month (head_approved, awaiting director approval)
        $audit10Date = now()->startOfMonth()->addDays(2);
        $audit10 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-010'],
            [
                'title' => 'Internal QMS Procedure Adherence & Records Audit',
                'type' => 'routine',
                'process_id' => $procQms->id,
                'checklist_id' => $procQms->id,
                'directorate_id' => $createdDirectorates['DSD']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'NCAA Headquarters, Abuja',
                'start_date' => $audit10Date,
                'scheduled_for' => $audit10Date,
                'status' => 'head_approved',
                'created_at' => $audit10Date,
                'updated_at' => now(),
            ]
        );
        AuditApproval::firstOrCreate(['audit_id' => $audit10->id, 'action' => 'head_approved'], [
            'user_id' => $seededUsers['head_of_audit']->id,
            'role' => 'head_of_audit',
            'comment' => 'Recommended for Director certification.',
            'created_at' => now()->subHours(12),
        ]);

        // Audit 11: returned status (covering all lifecycle statuses)
        $audit11 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-011'],
            [
                'title' => 'Ramp Turnaround Fuel Quality Spot Check — Returned for Clarity',
                'type' => 'unplanned',
                'process_id' => $procRamp->id,
                'checklist_id' => $procRamp->id,
                'directorate_id' => $createdDirectorates['DOLG']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Port Harcourt Airport',
                'start_date' => now()->subDays(6),
                'scheduled_for' => now()->subDays(6),
                'status' => 'returned',
                'created_at' => now()->subDays(6),
                'updated_at' => now()->subDays(1),
            ]
        );

        // Audit 12: Upcoming routine audit in next 18 days (scheduled, routine, within 30 days)
        $audit12 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-012'],
            [
                'title' => 'Avionics Secondary Inspection & Calibrated Store Audit',
                'type' => 'routine',
                'process_id' => $procAvionics->id,
                'checklist_id' => $procAvionics->id,
                'directorate_id' => $createdDirectorates['DAWS']->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'reviewer_id' => $seededUsers['reviewer']->id,
                'created_by' => $seededUsers['lead_auditor']->id,
                'location' => 'Lagos Maintenance Facility',
                'start_date' => now()->addDays(18),
                'scheduled_for' => now()->addDays(18),
                'status' => 'scheduled',
                'created_at' => now(),
            ]
        );

        // Seed Additional NCRs (Total >= 8 NCRs, >= 2 overdue CAPs)
        // NCR 3: Overdue CAP #2 (Level 1, cap_due_at past, target_date past)
        $ncr3 = Finding::firstOrCreate(
            ['reference' => 'NCR-2026-0003'],
            [
                'audit_id' => $audit1->id,
                'finding_number' => 'NCR-2026-0003',
                'title' => 'Uncalibrated Tire Pressure Gauge in Ramp Line Store',
                'level' => 1,
                'description' => 'Digital aircraft tire pressure gauge SN# TG-883 found in active flight line toolkit with calibration lapsed 30 days prior.',
                'condition' => 'Digital aircraft tire pressure gauge SN# TG-883 found in active flight line toolkit with calibration lapsed 30 days prior.',
                'requirement_ref' => 'Nig.CARs Part 6.5.2',
                'criteria' => 'Nig.CARs Part 6.5.2',
                'assigned_unit_id' => $createdUnits['FLT-RAMP']->id ?? null,
                'raised_by' => $seededUsers['reviewer']->id,
                'cap_due_at' => now()->subDays(3), // Overdue
                'due_date' => now()->subDays(3)->toDateString(),
                'status' => 'open',
            ]
        );
        CorrectiveActionPlan::firstOrCreate(
            ['ncr_id' => $ncr3->id],
            [
                'finding_id' => $ncr3->id,
                'proposed_action' => 'Gauge quarantine and recall of all shift tire pressure logs.',
                'root_cause_analysis' => 'Storeman failed to flag expired calibration tag.',
                'responsible_person' => 'Mr. Ibrahim Yakubu',
                'target_completion_date' => now()->subDays(2), // Overdue
                'target_date' => now()->subDays(2),
                'submitted_by' => $seededUsers['auditee']->id,
                'status' => 'submitted',
            ]
        );

        // NCR 4: Open NCR in DOLG (Level 2)
        $ncr4 = Finding::firstOrCreate(
            ['reference' => 'NCR-2026-0004'],
            [
                'audit_id' => $audit4->id,
                'finding_number' => 'NCR-2026-0004',
                'title' => 'FOD Storage Bin Cover Broken at Gate 2',
                'level' => 2,
                'description' => 'Tarmac FOD bin cover spring hinge broken, allowing loose debris to blow across taxiway Bravo.',
                'condition' => 'Tarmac FOD bin cover spring hinge broken.',
                'requirement_ref' => 'Nig.CARs Part 12.045 & ICAO Annex 14',
                'criteria' => 'Nig.CARs Part 12.045',
                'assigned_unit_id' => $createdUnits['FLT-RAMP']->id ?? null,
                'raised_by' => $seededUsers['reviewer']->id,
                'cap_due_at' => now()->addDays(5),
                'due_date' => now()->addDays(5)->toDateString(),
                'status' => 'open',
            ]
        );

        // NCR 5: Open NCR in DAWS (Level 2)
        $ncr5 = Finding::firstOrCreate(
            ['reference' => 'NCR-2026-0005'],
            [
                'audit_id' => $audit2->id,
                'finding_number' => 'NCR-2026-0005',
                'title' => 'Sealant Refrigeration Temperature Sensor Uncertified',
                'level' => 2,
                'description' => 'Composite storage freezer temperature display logger calibration certificate expired.',
                'condition' => 'Composite storage freezer temperature display logger expired.',
                'requirement_ref' => 'Nig.CARs Part 6.3.4',
                'criteria' => 'Nig.CARs Part 6.3.4',
                'assigned_unit_id' => $createdUnits['AIR-AMO']->id ?? null,
                'raised_by' => $seededUsers['lead_auditor']->id,
                'cap_due_at' => now()->addDays(10),
                'due_date' => now()->addDays(10)->toDateString(),
                'status' => 'open',
            ]
        );

        // NCR 6: Closed NCR in DAWS (Audit 5)
        $ncr6 = Finding::firstOrCreate(
            ['reference' => 'NCR-2026-0006'],
            [
                'audit_id' => $audit5->id,
                'finding_number' => 'NCR-2026-0006',
                'title' => 'AME Logbook Counter-Sign Missing on Avionics Inspection',
                'level' => 3,
                'description' => 'Minor paperwork gap: secondary supervisor stamp omitted on routine transponder check.',
                'condition' => 'Secondary supervisor stamp omitted.',
                'requirement_ref' => 'Nig.CARs Part 5.4.1',
                'criteria' => 'Nig.CARs Part 5.4.1',
                'assigned_unit_id' => $createdUnits['AIR-AMO']->id ?? null,
                'raised_by' => $seededUsers['reviewer']->id,
                'cap_due_at' => $audit5Date->copy()->addDays(14),
                'due_date' => $audit5Date->copy()->addDays(14)->toDateString(),
                'status' => 'closed',
            ]
        );
        CorrectiveActionPlan::firstOrCreate(
            ['ncr_id' => $ncr6->id],
            [
                'finding_id' => $ncr6->id,
                'proposed_action' => 'Sign-off added and training circular published.',
                'root_cause_analysis' => 'Shift handover oversight.',
                'responsible_person' => 'Engr. Folake Adeleke',
                'target_completion_date' => $audit5Date->copy()->addDays(10),
                'target_date' => $audit5Date->copy()->addDays(10),
                'submitted_by' => $seededUsers['auditee']->id,
                'status' => 'accepted',
            ]
        );

        // NCR 7: Closed NCR in DOLG (Audit 6)
        $ncr7 = Finding::firstOrCreate(
            ['reference' => 'NCR-2026-0007'],
            [
                'audit_id' => $audit6->id,
                'finding_number' => 'NCR-2026-0007',
                'title' => 'Hazmat Segregation Barrier Placard Missing',
                'level' => 2,
                'description' => 'Lithium ion packaging zone lacked class 9 hazard boundary placard in cargo hold 1.',
                'condition' => 'Lacked class 9 placard.',
                'requirement_ref' => 'Nig.CARs Part 9.2.1',
                'criteria' => 'Nig.CARs Part 9.2.1',
                'assigned_unit_id' => $createdUnits['FLT-RAMP']->id ?? null,
                'raised_by' => $seededUsers['reviewer']->id,
                'cap_due_at' => $audit6Date->copy()->addDays(7),
                'due_date' => $audit6Date->copy()->addDays(7)->toDateString(),
                'status' => 'closed',
            ]
        );
        CorrectiveActionPlan::firstOrCreate(
            ['ncr_id' => $ncr7->id],
            [
                'finding_id' => $ncr7->id,
                'proposed_action' => 'New metal hazard signs installed and ramp marshallers briefed.',
                'root_cause_analysis' => 'Wear and tear from weather.',
                'responsible_person' => 'Mr. Ibrahim Yakubu',
                'target_completion_date' => $audit6Date->copy()->addDays(5),
                'target_date' => $audit6Date->copy()->addDays(5),
                'submitted_by' => $seededUsers['auditee']->id,
                'status' => 'accepted',
            ]
        );

        // NCR 8: Open NCR in DSD (Audit 10)
        $ncr8 = Finding::firstOrCreate(
            ['reference' => 'NCR-2026-0008'],
            [
                'audit_id' => $audit10->id,
                'finding_number' => 'NCR-2026-0008',
                'title' => 'Auditor Training Currency Portfolio Missing Human Factors Refresh',
                'level' => 3,
                'description' => 'Internal auditor training logbook lacked 24-month Human Factors recurrent certificate.',
                'condition' => 'Training logbook lacked Human Factors certificate.',
                'requirement_ref' => 'ICAO Doc 9859 SMS Manual',
                'criteria' => 'ICAO Doc 9859',
                'assigned_unit_id' => $createdUnits['QA-AUD']->id ?? null,
                'raised_by' => $seededUsers['head_of_audit']->id,
                'cap_due_at' => now()->addDays(14),
                'due_date' => now()->addDays(14)->toDateString(),
                'status' => 'open',
            ]
        );
    }
}
