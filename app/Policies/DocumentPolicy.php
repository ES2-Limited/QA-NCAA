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
        return $user->can('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $user->can('documents.view');
    }

    public function download(User $user, Document $document): bool
    {
        return $user->can('documents.download');
    }

    public function create(User $user): bool
    {
        return $user->can('documents.upload');
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('documents.delete');
    }
}
