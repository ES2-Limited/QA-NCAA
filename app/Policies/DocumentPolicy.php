<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor', 'head_of_audit', 'director']);
    }

    public function view(User $user, Document $document): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor', 'head_of_audit', 'director']);
    }

    public function download(User $user, Document $document): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor', 'head_of_audit', 'director']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['system_admin', 'lead_auditor']);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->hasRole('system_admin');
    }
}
