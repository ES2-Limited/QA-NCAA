<?php

namespace Database\Seeders;

use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;

class WorkflowStepSeeder extends Seeder
{
    /**
     * Default 3-step approval chain: Reviewer -> Head of Audit -> Director.
     */
    public static function defaults(): array
    {
        return [
            [
                'order' => 1,
                'name' => 'Technical Quality Review',
                'role' => 'reviewer',
                'action_status' => 'reviewed',
                'can_return' => true,
                'required' => true,
                'is_final' => false,
            ],
            [
                'order' => 2,
                'name' => 'Head of Audit Executive Endorsement',
                'role' => 'head_of_audit',
                'action_status' => 'head_approved',
                'can_return' => true,
                'required' => true,
                'is_final' => false,
            ],
            [
                'order' => 3,
                'name' => 'Directorate Final Certification',
                'role' => 'director',
                'action_status' => 'director_approved',
                'can_return' => true,
                'required' => true,
                'is_final' => true,
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::defaults() as $step) {
            WorkflowStep::updateOrCreate(
                ['order' => $step['order']],
                $step
            );
        }
    }
}
