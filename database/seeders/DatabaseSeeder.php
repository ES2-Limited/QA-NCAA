<?php

namespace Database\Seeders;

use App\Models\Audit;
use App\Models\AuditResponse;
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

        // 1. Spatie RBAC Roles (Exactly the 7 requested roles)
        $roles = [
            'system_admin',
            'lead_auditor',
            'field_auditor',
            'reviewer',
            'head_of_audit',
            'director',
            'auditee',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

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
        }

        // 4. Document Library Sample Files & Seed Records (Seed before processes so documents can be linked)
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

        CorrectiveActionPlan::firstOrCreate(
            ['finding_id' => $fnd1->id],
            [
                'proposed_action' => 'Replaced bonding lead assembly with brand new certified copper braided wire and performed 0.5 ohm continuity certification.',
                'root_cause_analysis' => 'Maintenance shift handover failure caused weekly test log skip.',
                'responsible_person' => 'Station Fueling Ops Lead (Abuja Station)',
                'target_completion_date' => now()->addDays(3),
                'status' => 'pending_approval',
            ]
        );
    }
}
