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
        return $user->can('processes.view');
    }

    public function view(User $user, Process $process): bool
    {
        return $user->can('processes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('processes.create');
    }

    public function update(User $user, Process $process): bool
    {
        return $user->can('processes.edit');
    }

    public function delete(User $user, Process $process): bool
    {
        return $user->can('processes.delete');
    }
}
