<?php

namespace App\Policies;

use App\Models\Audit;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any audits.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor', 'head_of_audit', 'director', 'field_auditor', 'reviewer']);
    }

    /**
     * Determine whether the user can view the audit.
     */
    public function view(User $user, Audit $audit): bool
    {
        // System admin, lead auditor, head of audit, director can view all audits
        if ($user->hasAnyRole(['system_admin', 'lead_auditor', 'head_of_audit', 'director'])) {
            return true;
        }

        // Field auditor: must be assigned as lead auditor or team auditor
        if ($user->hasRole('field_auditor')) {
            if ($audit->lead_auditor_id === $user->id) {
                return true;
            }

            return $audit->auditors()->where('users.id', $user->id)->exists();
        }

        // Reviewer: must be assigned reviewer
        if ($user->hasRole('reviewer')) {
            return $audit->reviewer_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can conduct/execute field work on the audit.
     */
    public function conduct(User $user, Audit $audit): bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        if (! $user->hasRole('field_auditor')) {
            return false;
        }

        if ($audit->lead_auditor_id === $user->id) {
            return true;
        }

        return $audit->auditors()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can review the audit.
     */
    public function review(User $user, Audit $audit): bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        if (! $user->hasRole('reviewer')) {
            return false;
        }

        return $audit->reviewer_id === $user->id;
    }

    /**
     * Determine whether the user can approve the audit.
     */
    public function approve(User $user, Audit $audit): bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        // Check if the user is authorized for any configured workflow step
        $workflow = app(WorkflowService::class);
        $steps = $workflow->getSteps();
        foreach ($steps as $step) {
            if ($workflow->userCanActOnStep($user, $step)) {
                return true;
            }
        }

        return $user->hasAnyRole(['head_of_audit', 'director', 'reviewer']);
    }

    /**
     * Determine whether the user can create audits.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor']);
    }

    /**
     * Determine whether the user can update the audit.
     */
    public function update(User $user, Audit $audit): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor']);
    }

    /**
     * Determine whether the user can delete the audit.
     */
    public function delete(User $user, Audit $audit): bool
    {
        return $user->hasRole('system_admin');
    }
}
