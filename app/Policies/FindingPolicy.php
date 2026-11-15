<?php

namespace App\Policies;

use App\Models\Finding;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FindingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any NCRs.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ncrs.view');
    }

    /**
     * Determine whether the user can view the specific NCR.
     */
    public function view(User $user, Finding $finding): bool
    {
        if (! $user->can('ncrs.view')) {
            return false;
        }

        // Broad privileged permissions (lead auditor, director, head of audit, system admin)
        if ($user->can('audits.schedule') || $user->can('approvals.endorse') || $user->can('approvals.final') || $user->can('settings.manage')) {
            return true;
        }

        // Reviewer assigned to the audit can view
        if ($user->can('audits.review') && $finding->audit?->reviewer_id === $user->id) {
            return true;
        }

        // Field auditor assigned to the audit can view
        if ($user->can('audits.conduct')) {
            if ($finding->audit?->lead_auditor_id === $user->id) {
                return true;
            }
            if ($finding->audit?->auditors()->where('users.id', $user->id)->exists()) {
                return true;
            }
        }

        // Auditee: must belong to the same Unit or Directorate as the NCR or Audit
        if ($user->can('caps.submit')) {
            // Check direct unit match
            if ($finding->assigned_unit_id && $user->unit_id === $finding->assigned_unit_id) {
                return true;
            }

            // Check audit's process unit
            if ($finding->audit?->process?->unit_id && $user->unit_id === $finding->audit->process->unit_id) {
                return true;
            }

            // Check directorate match
            if ($finding->audit?->directorate_id && $user->directorate_id === $finding->audit->directorate_id) {
                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can submit a CAP for the NCR.
     */
    public function submitCap(User $user, Finding $finding): bool
    {
        if ($user->can('settings.manage')) {
            return true;
        }

        if (! $user->can('caps.submit')) {
            return false;
        }

        // Must have view access to the NCR
        return $this->view($user, $finding);
    }

    /**
     * Determine whether the user can review/accept/reject a CAP.
     */
    public function reviewCap(User $user, Finding $finding): bool
    {
        return $user->can('caps.review');
    }

    /**
     * Determine whether the user can create findings.
     */
    public function create(User $user): bool
    {
        return $user->can('ncrs.raise');
    }

    /**
     * Determine whether the user can update the finding.
     */
    public function update(User $user, Finding $finding): bool
    {
        if ($user->can('ncrs.edit') || $user->can('settings.manage')) {
            return true;
        }

        if ($user->can('audits.conduct') && $finding->audit?->lead_auditor_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the finding.
     */
    public function delete(User $user, Finding $finding): bool
    {
        return $user->can('ncrs.delete');
    }
}
