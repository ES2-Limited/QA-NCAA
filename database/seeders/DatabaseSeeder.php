<?php

namespace Database\Seeders;

use App\Models\Audit;
use App\Models\AuditResponse;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\CorrectiveActionPlan;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Finding;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
                'directorate_code' => 'DOLG',
                'department_code' => 'DOLG-FLT',
                'unit_code' => 'FLT-RAMP',
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

        // 4. Standard Checklists & Items
        $chkGround = Checklist::firstOrCreate(
            ['code' => 'CHK-GRD-2026'],
            [
                'title' => 'Ramp Safety & Ground Handling Quality Surveillance',
                'category' => 'ground_ops',
                'description' => 'Comprehensive apron and ramp inspection protocol based on Nig.CARs Part 8.4 and ICAO Doc 10121.',
                'is_active' => true,
            ]
        );

        $groundItems = [
            [
                'item_code' => 'GRD-01',
                'requirement' => 'All Ground Support Equipment (GSE) on active ramp must possess valid NCAA inspection stickers and functional wheel chocks.',
                'guidance' => 'Inspect tugs, belt loaders, and fuel bowsers for fuel leaks and valid certification tags.',
                'regulatory_reference' => 'Nig.CARs Part 8.4.1.2 & ICAO Annex 14',
                'order_index' => 1,
            ],
            [
                'item_code' => 'GRD-02',
                'requirement' => 'Foreign Object Debris (FOD) mitigation sweep logs must be recorded at every gate prior to scheduled aircraft arrivals.',
                'guidance' => 'Verify gate logbook entries and inspect ramp bin cleanliness within 15 meters of aircraft parking.',
                'regulatory_reference' => 'ICAO Doc 9137 Part 2 & Nig.CARs Part 12',
                'order_index' => 2,
            ],
            [
                'item_code' => 'GRD-03',
                'requirement' => 'Marshaling and wing-walker personnel must wear high-visibility PPE, noise attenuators, and maintain clear two-way comms with cockpit.',
                'guidance' => 'Check active ramp crew for compliant safety vest, earmuffs, and illuminated wands during dusk operations.',
                'regulatory_reference' => 'Nig.CARs Part 8.5.3',
                'order_index' => 3,
            ],
            [
                'item_code' => 'GRD-04',
                'requirement' => 'Aviation fuel hydrant bonding and earthing cable verification prior to fuel delivery into aircraft tanks.',
                'guidance' => 'Verify static earthing reel continuity and bowser meter calibration certificate.',
                'regulatory_reference' => 'Nig.CARs Part 8.8.2.1',
                'order_index' => 4,
            ],
            [
                'item_code' => 'GRD-05',
                'requirement' => 'Baggage make-up area security access control and 100% hold-baggage reconciliation before cargo door lock.',
                'guidance' => 'Inspect access biometric turnstile and audit sample manifest against BRS.',
                'regulatory_reference' => 'Nig.CARs Part 17 AVSEC & ICAO Annex 17',
                'order_index' => 5,
            ],
        ];

        foreach ($groundItems as $item) {
            ChecklistItem::firstOrCreate(
                ['checklist_id' => $chkGround->id, 'item_code' => $item['item_code']],
                $item
            );
        }

        // 5. Audit Engagements
        $audit1 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-001'],
            [
                'title' => 'Ramp Safety & Fuel Quality Surveillance Audit - Nnamdi Azikiwe Int\'l Airport',
                'type' => 'surveillance',
                'directorate_id' => $createdDirectorates['DOLG']->id,
                'checklist_id' => $chkGround->id,
                'lead_auditor_id' => $seededUsers['lead_auditor']->id,
                'location' => 'Abuja Airport (DNAA / ABV)',
                'start_date' => now()->subDays(2),
                'status' => 'in_progress',
                'scope' => 'Concourse B Apron, Hydrant Fueling Pit 4, Baggage Make-up Area, and GSE Maintenance Workshop.',
                'executive_summary' => 'Routine surprise surveillance conducted jointly by NCAA DSD and Flight Safety inspectors.',
                'ai_executive_summary' => "### Executive Audit Summary (NCAA Directorate of Special Duties)\n**Engagement:** Ramp Safety & Fuel Quality Surveillance Audit - Abuja\n**Audited Directorate/Station:** Directorate of Operations, Licensing and Training (DOLG)\n**Overall Quality Compliance Index:** 80%\n\n#### 1. Strategic Compliance Assessment\nThe DSD Internal Audit team concluded surveillance inspection at Abuja International Airport. Overall adherence meets basic NCAA criteria.\n\n#### 2. Summary of Findings\n- **Major Operational Findings:** 1 (GSE uncalibrated fuel hydrant static line)\n- **Minor Discrepancies:** 1 (FOD bin documentation delay)",
            ]
        );

        foreach (ChecklistItem::where('checklist_id', $chkGround->id)->get() as $it) {
            $status = ($it->item_code === 'GRD-04') ? 'non_compliant' : 'compliant';
            $notes = ($it->item_code === 'GRD-04') ? 'Static bonding cable showed physical outer insulation wear and missing continuity tag.' : 'Verified compliant during physical ramp inspection.';

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
