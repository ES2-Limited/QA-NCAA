<?php

namespace Database\Seeders;

use App\Models\Audit;
use App\Models\AuditResponse;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\CorrectiveActionPlan;
use App\Models\Directorate;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Key NCAA Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@ncaa.gov.ng'],
            [
                'name' => 'Capt. Musa Bello',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'department' => 'Directorate of Special Duties (DSD)',
                'staff_id' => 'NCAA/DSD/001',
                'phone' => '+234 803 123 4567',
            ]
        );

        $auditor1 = User::firstOrCreate(
            ['email' => 'auditor@ncaa.gov.ng'],
            [
                'name' => 'Engr. Folake Adeleke',
                'password' => Hash::make('password'),
                'role' => 'lead_auditor',
                'department' => 'Quality Assurance & Internal Audit Unit',
                'staff_id' => 'NCAA/QA/042',
                'phone' => '+234 802 987 6543',
            ]
        );

        $rep = User::firstOrCreate(
            ['email' => 'operations@ncaa.gov.ng'],
            [
                'name' => 'Mr. Ibrahim Yakubu',
                'password' => Hash::make('password'),
                'role' => 'directorate_rep',
                'department' => 'Directorate of Airworthiness Standards (DAWS)',
                'staff_id' => 'NCAA/DAWS/109',
                'phone' => '+234 805 555 1122',
            ]
        );

        // 2. Create NCAA Directorates
        $directorates = [
            [
                'code' => 'DSD',
                'name' => 'Directorate of Special Duties',
                'head_of_directorate' => 'Director of Special Duties',
                'email' => 'dsd@ncaa.gov.ng',
                'description' => 'Oversees specialized audits, strategic quality assurance, presidential VIP flight protocols, and ministerial directives.',
            ],
            [
                'code' => 'DAWS',
                'name' => 'Directorate of Airworthiness Standards',
                'head_of_directorate' => 'Engr. K. O. Balogun',
                'email' => 'airworthiness@ncaa.gov.ng',
                'description' => 'Aircraft registration, airworthiness certification, maintenance organization approval, and continuing safety monitoring.',
            ],
            [
                'code' => 'DOLG',
                'name' => 'Directorate of Operations, Licensing and Training',
                'head_of_directorate' => 'Capt. S. A. Danbaba',
                'email' => 'operations@ncaa.gov.ng',
                'description' => 'AOC certification, flight crew licensing, recurrent simulator audits, and aerodrome operational compliance.',
            ],
            [
                'code' => 'DATR',
                'name' => 'Directorate of Air Transport Regulation',
                'head_of_directorate' => 'Mrs. Nneka Eze',
                'email' => 'datr@ncaa.gov.ng',
                'description' => 'Economic oversight of airlines, bilateral air service agreements (BASA), and commercial consumer protection.',
            ],
            [
                'code' => 'DAAS',
                'name' => 'Directorate of Aerodrome & Airspace Standards',
                'head_of_directorate' => 'Engr. T. O. Okon',
                'email' => 'aerodrome@ncaa.gov.ng',
                'description' => 'Airport safety certification, runway lighting, fire fighting category compliance, and navigational aids.',
            ],
        ];

        $dirModels = [];
        foreach ($directorates as $dir) {
            $dirModels[$dir['code']] = Directorate::firstOrCreate(['code' => $dir['code']], $dir);
        }

        // 3. Create Standard NCAA / ICAO Audit Checklists
        $chkGround = Checklist::firstOrCreate(
            ['code' => 'CHK-GRD-2026'],
            [
                'title' => 'Ramp Safety & Ground Handling Quality Surveillance',
                'category' => 'ground_ops',
                'description' => 'Comprehensive apron and ramp inspection protocol based on Nig.CARs Part 8.4 and ICAO Doc 10121 (Ground Handling Manual).',
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
                'guidance' => 'Inspect access biometric turnstile and audit sample manifest against BRS (Baggage Reconciliation System).',
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

        $chkAir = Checklist::firstOrCreate(
            ['code' => 'CHK-AIR-2026'],
            [
                'title' => 'Aircraft Maintenance & Continuing Airworthiness Audit',
                'category' => 'airworthiness',
                'description' => 'Hangar, Technical Logbook, and Maintenance Organization compliance against Nig.CARs Part 5 & 6.',
                'is_active' => true,
            ]
        );

        $airItems = [
            [
                'item_code' => 'AIR-01',
                'requirement' => 'Aircraft Technical Logbook (Tech Log) must contain accurate flight hours, cycles, defect rectifications, and licensed engineer signatures.',
                'guidance' => 'Audit past 30 days of Tech Log sheets for MEL item sign-offs and Deferred Defect records.',
                'regulatory_reference' => 'Nig.CARs Part 5.4.1',
                'order_index' => 1,
            ],
            [
                'item_code' => 'AIR-02',
                'requirement' => 'Mandatory Tool Control & Calibration register must show current validity for all torque wrenches, pitot-static testers, and avionics test benches.',
                'guidance' => 'Randomly sample 5 calibrated tools in the avionics bay and verify ISO 17025 calibration stamps.',
                'regulatory_reference' => 'Nig.CARs Part 6.5.2',
                'order_index' => 2,
            ],
            [
                'item_code' => 'AIR-03',
                'requirement' => 'Chemical and component storage in temperature-controlled bonded stores with valid Airworthiness Release Certificates (Form One / 8130-3).',
                'guidance' => 'Inspect shelf-life expirations for aerospace sealants and composite adhesives.',
                'regulatory_reference' => 'ICAO Annex 8 Airworthiness & Nig.CARs Part 6',
                'order_index' => 3,
            ],
        ];

        foreach ($airItems as $item) {
            ChecklistItem::firstOrCreate(
                ['checklist_id' => $chkAir->id, 'item_code' => $item['item_code']],
                $item
            );
        }

        // 4. Create Realistic NCAA Audit Engagements
        $audit1 = Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-001'],
            [
                'title' => 'Ramp Safety & Fuel Quality Surveillance Audit - Nnamdi Azikiwe Int\'l Airport',
                'type' => 'surveillance',
                'directorate_id' => $dirModels['DOLG']->id,
                'checklist_id' => $chkGround->id,
                'lead_auditor_id' => $auditor1->id,
                'location' => 'Abuja Airport (DNAA / ABV)',
                'start_date' => now()->subDays(2),
                'end_date' => null,
                'status' => 'in_progress',
                'scope' => 'Concourse B Apron, Hydrant Fueling Pit 4, Baggage Make-up Area, and GSE Maintenance Workshop.',
                'executive_summary' => 'Routine surprise surveillance conducted jointly by NCAA DSD and Flight Safety inspectors.',
                'ai_executive_summary' => "### Executive Audit Summary (NCAA Directorate of Special Duties)\n**Engagement:** Ramp Safety & Fuel Quality Surveillance Audit - Abuja\n**Audited Directorate/Station:** Directorate of Operations, Licensing and Training (DOLG)\n**Overall Quality Compliance Index:** 80%\n\n#### 1. Strategic Compliance Assessment\nThe DSD Internal Audit team concluded surveillance inspection at Abuja International Airport. Overall adherence meets basic NCAA criteria, though ground equipment maintenance gaps require swift corrective actions.\n\n#### 2. Summary of Findings\n- **Critical Findings:** 0\n- **Major Operational Findings:** 1 (GSE uncalibrated fuel hydrant static line)\n- **Minor Discrepancies:** 1 (FOD bin documentation delay)\n\n#### 3. Risk Horizon & Auditor Conclusion\nRemediation has been formally initiated. All ground handling partners are notified to comply within 21 calendar days.",
            ]
        );

        // Pre-fill responses for audit 1
        $groundItemModels = ChecklistItem::where('checklist_id', $chkGround->id)->get();
        foreach ($groundItemModels as $it) {
            $status = ($it->item_code === 'GRD-04') ? 'non_compliant' : 'compliant';
            $notes = ($it->item_code === 'GRD-04') ? 'Static bonding cable showed physical outer insulation wear and missing continuity tag.' : 'Verified compliant during physical ramp inspection.';

            AuditResponse::firstOrCreate(
                ['audit_id' => $audit1->id, 'checklist_item_id' => $it->id],
                [
                    'status' => $status,
                    'notes' => $notes,
                    'auditor_id' => $auditor1->id,
                ]
            );
        }

        // Create Finding for the non-conformance
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

        // Audit 2: Scheduled
        Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-002'],
            [
                'title' => 'Hangar Quality & Tool Calibration Audit - Murtala Muhammed Airport',
                'type' => 'internal_audit',
                'directorate_id' => $dirModels['DAWS']->id,
                'checklist_id' => $chkAir->id,
                'lead_auditor_id' => $admin->id,
                'location' => 'Lagos Airport (DNMM / LOS)',
                'start_date' => now()->addDays(4),
                'status' => 'scheduled',
                'scope' => 'Approved Maintenance Organization (AMO) facilities, tool stores, and Tech Log archive at Ikeja Hangar 2.',
            ]
        );

        // Audit 3: Completed
        Audit::firstOrCreate(
            ['reference_number' => 'AUD-2026-DSD-003'],
            [
                'title' => 'VIP Flight Support & Special Operations Compliance Review',
                'type' => 'special_inspection',
                'directorate_id' => $dirModels['DSD']->id,
                'checklist_id' => $chkGround->id,
                'lead_auditor_id' => $auditor1->id,
                'location' => 'Presidential Wing, Nnamdi Azikiwe Airport Abuja',
                'start_date' => now()->subDays(10),
                'end_date' => now()->subDays(8),
                'status' => 'completed',
                'scope' => 'Specialized flight handling, security clearances, and ramp escort compliance.',
                'executive_summary' => '100% compliance verified across all security screening and ramp coordination checkpoints.',
                'ai_executive_summary' => "### Executive Audit Summary (NCAA Directorate of Special Duties)\n**Engagement:** VIP Flight Support & Special Operations Compliance Review\n**Audited Directorate/Station:** Directorate of Special Duties (DSD)\n**Overall Quality Compliance Index:** 100%\n\n#### 1. Strategic Compliance Assessment\nAll evaluated checkpoints conformed fully with presidential flight security directives and Nig.CARs standards. No deficiencies recorded.",
            ]
        );
    }
}
