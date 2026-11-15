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
        return $user->can('audits.view');
    }

    /**
     * Determine whether the user can view the audit.
     */
    public function view(User $user, Audit $audit): bool
    {
        // Users with general audits.view (system_admin, lead_auditor, head_of_audit, director)
        if ($user->can('audits.view')) {
            // If the user also has conduct/review restrictions, check assignment if they don't have higher permissions
            if ($user->can('audits.schedule') || $user->can('approvals.endorse') || $user->can('approvals.final') || $user->can('settings.manage')) {
                return true;
            }

            // Reviewer: must be assigned reviewer if they only have audits.review
            if ($user->can('audits.review') && $audit->reviewer_id === $user->id) {
                return true;
            }

            // Field auditor: must be assigned as lead auditor or team auditor
            if ($user->can('audits.conduct')) {
                if ($audit->lead_auditor_id === $user->id) {
                    return true;
                }

                return $audit->auditors()->where('users.id', $user->id)->exists();
            }

            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can conduct/execute field work on the audit.
     */
    public function conduct(User $user, Audit $audit): bool
    {
        if ($user->can('settings.manage')) {
            return true;
        }

        if (! $user->can('audits.conduct')) {
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
        if ($user->can('settings.manage')) {
            return true;
        }

        if (! $user->can('audits.review')) {
            return false;
        }

        return $audit->reviewer_id === $user->id;
    }

    /**
     * Determine whether the user can approve the audit.
     */
    public function approve(User $user, Audit $audit): bool
    {
        if ($user->can('settings.manage')) {
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

        return $user->can('approvals.endorse') || $user->can('approvals.final') || $user->can('audits.review');
    }

    /**
     * Determine whether the user can create/schedule audits.
     */
    public function create(User $user): bool
    {
        return $user->can('audits.schedule');
    }

    /**
     * Determine whether the user can update the audit.
     */
    public function update(User $user, Audit $audit): bool
    {
        return $user->can('audits.update') || $user->can('audits.schedule');
    }

    /**
     * Determine whether the user can delete the audit.
     */
    public function delete(User $user, Audit $audit): bool
    {
        return $user->can('audits.delete');
    }
}
