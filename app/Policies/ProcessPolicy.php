<?php

namespace App\Policies;

use App\Models\Process;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcessPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor', 'head_of_audit', 'director']);
    }

    public function view(User $user, Process $process): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor', 'head_of_audit', 'director']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor']);
    }

    public function update(User $user, Process $process): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor']);
    }

    public function delete(User $user, Process $process): bool
    {
        return $user->hasRole('system_admin');
    }
}
