<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lookups = [
            // 1. Process categories
            [
                'type' => 'process_category',
                'key' => 'licensing',
                'name' => 'Licensing',
                'colour' => 'blue',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'process_category',
                'key' => 'certification',
                'name' => 'Certification',
                'colour' => 'indigo',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'process_category',
                'key' => 'surveillance',
                'name' => 'Surveillance',
                'colour' => 'emerald',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'process_category',
                'key' => 'enforcement',
                'name' => 'Enforcement',
                'colour' => 'rose',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'process_category',
                'key' => 'flight_safety',
                'name' => 'Flight Safety',
                'colour' => 'cyan',
                'sort_order' => 5,
                'is_active' => true,
            ],

            // 2. Document types
            [
                'type' => 'document_type',
                'key' => 'guideline',
                'name' => 'Guideline',
                'colour' => 'blue',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'document_type',
                'key' => 'sop',
                'name' => 'SOP',
                'colour' => 'indigo',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'document_type',
                'key' => 'regulation',
                'name' => 'Regulation',
                'colour' => 'amber',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'document_type',
                'key' => 'form',
                'name' => 'Form',
                'colour' => 'emerald',
                'sort_order' => 4,
                'is_active' => true,
            ],

            // 3. Audit statuses
            [
                'type' => 'audit_status',
                'key' => 'scheduled',
                'name' => 'Scheduled',
                'colour' => 'zinc',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'audit_status',
                'key' => 'in_progress',
                'name' => 'In Progress',
                'colour' => 'amber',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'audit_status',
                'key' => 'submitted',
                'name' => 'Submitted',
                'colour' => 'cyan',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'audit_status',
                'key' => 'reviewed',
                'name' => 'Reviewed',
                'colour' => 'blue',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'audit_status',
                'key' => 'head_approved',
                'name' => 'Head Approved',
                'colour' => 'indigo',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'type' => 'audit_status',
                'key' => 'director_approved',
                'name' => 'Director Approved',
                'colour' => 'emerald',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'type' => 'audit_status',
                'key' => 'returned',
                'name' => 'Returned',
                'colour' => 'rose',
                'sort_order' => 7,
                'is_active' => true,
            ],

            // 4. NCR statuses
            [
                'type' => 'ncr_status',
                'key' => 'open',
                'name' => 'Open',
                'colour' => 'rose',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'ncr_status',
                'key' => 'cap_submitted',
                'name' => 'CAP Submitted',
                'colour' => 'cyan',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'ncr_status',
                'key' => 'cap_accepted',
                'name' => 'CAP Accepted',
                'colour' => 'emerald',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'ncr_status',
                'key' => 'cap_rejected',
                'name' => 'CAP Rejected',
                'colour' => 'amber',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'ncr_status',
                'key' => 'closed',
                'name' => 'Closed',
                'colour' => 'zinc',
                'sort_order' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($lookups as $item) {
            Lookup::updateOrCreate(
                ['type' => $item['type'], 'key' => $item['key']],
                $item
            );
        }
    }
}
