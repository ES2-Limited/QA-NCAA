<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\AuditApproval;
use App\Models\WorkflowStep;
use Database\Seeders\WorkflowStepSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class WorkflowService
{
    /**
     * Get all configured workflow steps in sequence order.
     */
    public function getSteps(): Collection
    {
        $steps = WorkflowStep::ordered()->get();

        if ($steps->isEmpty()) {
            return collect(WorkflowStepSeeder::defaults())->map(function ($d, $idx) {
                return new WorkflowStep(array_merge($d, ['id' => $idx + 1]));
            });
        }

        return $steps;
    }

    /**
     * Get the active/current workflow step for an audit based on its status.
     * When audit is 'submitted' or 'returned', it is on step 1.
     * When it matches a step's action_status, it moves to the next step.
     */
    public function getCurrentStep(Audit $audit): ?WorkflowStep
    {
        $steps = $this->getSteps();
        if ($steps->isEmpty()) {
            return null;
        }

        // When submitted or returned, it is on the very first step
        if ($audit->status === 'submitted' || $audit->status === 'returned') {
            return $steps->first();
        }

        // Find which step was last achieved
        foreach ($steps as $index => $step) {
            if ($audit->status === $step->action_status) {
                // If this is the final step, audit is complete
                if ($step->is_final || ! isset($steps[$index + 1])) {
                    return null;
                }

                // Otherwise, it is waiting at the next step
                return $steps[$index + 1];
            }
        }

        return null;
    }

    /**
     * Get the step that comes after the given step.
     */
    public function getNextStep(WorkflowStep $currentStep): ?WorkflowStep
    {
        $steps = $this->getSteps();
        $found = false;

        foreach ($steps as $step) {
            if ($found) {
                return $step;
            }
            if ($step->id === $currentStep->id || $step->order === $currentStep->order) {
                $found = true;
            }
        }

        return null;
    }

    /**
     * Get the step that precedes the given step.
     */
    public function getPreviousStep(WorkflowStep $currentStep): ?WorkflowStep
    {
        $steps = $this->getSteps();
        $prev = null;

        foreach ($steps as $step) {
            if ($step->id === $currentStep->id || $step->order === $currentStep->order) {
                return $prev;
            }
            $prev = $step;
        }

        return null;
    }

    /**
     * Check if a user has permission/role to act on the given workflow step.
     */
    public function userCanActOnStep($user, WorkflowStep $step): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('system_admin')) {
            return true;
        }

        if ($user->hasRole($step->role)) {
            return true;
        }

        try {
            return method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($step->role);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Execute an approval action for the current step.
     */
    public function approveStep(Audit $audit, $user, ?string $comment = null): array
    {
        $step = $this->getCurrentStep($audit);
        if (! $step) {
            throw new \RuntimeException('No active workflow step found for this audit.');
        }

        if (! $this->userCanActOnStep($user, $step)) {
            throw new AuthorizationException("User is not authorized for workflow step: {$step->name}");
        }

        // Record audit approval with step name and role
        AuditApproval::create([
            'audit_id' => $audit->id,
            'user_id' => $user->id,
            'role' => $step->role,
            'action' => $step->action_status,
            'comment' => $comment ?: "Approved step: {$step->name}",
        ]);

        $isFinal = (bool) $step->is_final || ! $this->getNextStep($step);

        $updateData = [
            'status' => $step->action_status,
        ];

        if ($isFinal) {
            $updateData['end_date'] = now();
        }

        $audit->update($updateData);

        // If this is the final step, generate the certificate
        $cert = null;
        if ($isFinal) {
            $generator = app(CertificateGenerator::class);
            $cert = $generator->generate($audit, $user->id);
        }

        return [
            'step' => $step,
            'is_final' => $isFinal,
            'certificate' => $cert,
        ];
    }

    /**
     * Execute a return action from the current step to the previous step (or field auditor).
     */
    public function returnStep(Audit $audit, $user, string $comment): WorkflowStep|string
    {
        $step = $this->getCurrentStep($audit);
        if (! $step) {
            throw new \RuntimeException('No active workflow step found for this audit.');
        }

        if (! $step->can_return) {
            throw new \RuntimeException("Step '{$step->name}' does not allow returning audits.");
        }

        if (! $this->userCanActOnStep($user, $step)) {
            throw new AuthorizationException("User is not authorized for workflow step: {$step->name}");
        }

        $prevStep = $this->getPreviousStep($step);

        if ($prevStep) {
            // Roll back to the status that puts the audit back in prevStep's hands:
            // What status puts an audit at prevStep?
            // If prevStep is the first step, that status is 'submitted' (or 'returned').
            // If prevStep has a step before it (prevPrevStep), the status that made prevStep current was prevPrevStep->action_status.
            $prevPrevStep = $this->getPreviousStep($prevStep);
            $newStatus = $prevPrevStep ? $prevPrevStep->action_status : 'returned';
            $returnTarget = $prevStep;
        } else {
            // First step returns back to field auditor
            $newStatus = 'returned';
            $returnTarget = 'field_auditor';
        }

        $audit->update(['status' => $newStatus]);

        AuditApproval::create([
            'audit_id' => $audit->id,
            'user_id' => $user->id,
            'role' => $user->roles->first()?->name ?? $step->role,
            'action' => 'returned',
            'comment' => "[Returned from {$step->name}]: {$comment}",
        ]);

        return $returnTarget;
    }
}
