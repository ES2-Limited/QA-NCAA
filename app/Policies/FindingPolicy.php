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
        return $user->hasAnyRole(['system_admin', 'lead_auditor', 'head_of_audit', 'director', 'reviewer', 'auditee', 'field_auditor']);
    }

    /**
     * Determine whether the user can view the specific NCR.
     */
    public function view(User $user, Finding $finding): bool
    {
        // System admin, lead auditor, head of audit, director can view all NCRs
        if ($user->hasAnyRole(['system_admin', 'lead_auditor', 'head_of_audit', 'director'])) {
            return true;
        }

        // Reviewer assigned to the audit can view
        if ($user->hasRole('reviewer') && $finding->audit?->reviewer_id === $user->id) {
            return true;
        }

        // Field auditor assigned to the audit can view
        if ($user->hasRole('field_auditor')) {
            if ($finding->audit?->lead_auditor_id === $user->id) {
                return true;
            }
            if ($finding->audit?->auditors()->where('users.id', $user->id)->exists()) {
                return true;
            }
        }

        // Auditee: must belong to the same Unit or Directorate as the NCR or Audit
        if ($user->hasRole('auditee')) {
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

        return false;
    }

    /**
     * Determine whether the user can submit a CAP for the NCR.
     */
    public function submitCap(User $user, Finding $finding): bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        if (! $user->hasRole('auditee')) {
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
        return $user->hasAnyRole(['system_admin', 'lead_auditor', 'reviewer']);
    }

    /**
     * Determine whether the user can create findings.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor', 'field_auditor']);
    }

    /**
     * Determine whether the user can update the finding.
     */
    public function update(User $user, Finding $finding): bool
    {
        if ($user->hasAnyRole(['system_admin', 'lead_auditor'])) {
            return true;
        }

        if ($user->hasRole('field_auditor') && $finding->audit?->lead_auditor_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the finding.
     */
    public function delete(User $user, Finding $finding): bool
    {
        return $user->hasRole('system_admin');
    }
}
