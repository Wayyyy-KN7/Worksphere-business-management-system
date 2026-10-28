<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document)
    {
        return $document->visibleTo($user);
    }

    public function download(User $user, Document $document): bool
    {
        return $document->canDownload($user);
    }

    public function update(User $user, Document $document)
    {
        return $document->canEdit($user);
    }

    public function share(User $user, Document $document): bool
    {
        return $document->canBeSharedBy($user);
    }

    public function delete(User $user, Document $document)
    {
        return $user->isAdmin() || $document->uploaded_by === $user->id;
    }

    public function approve(User $user, Document $document)
    {
        return $document->canBeApprovedBy($user);
    }
}