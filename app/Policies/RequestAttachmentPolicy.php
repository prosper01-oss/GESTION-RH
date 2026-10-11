<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\RequestAttachment;
use App\Models\User;

class RequestAttachmentPolicy
{
    /**
     * Un justificatif médical n'est visible que de l'agent et des personnes habilitées (REC-09).
     */
    public function view(User $user, RequestAttachment $attachment): bool
    {
        if ($attachment->request->isOwnedBy($user)) {
            return true;
        }

        if ($attachment->is_medical) {
            return $user->hasPermission(PermissionName::ViewMedicalAttachments);
        }

        return $user->hasPermission(PermissionName::ReviewRequests) || $user->hasPermission(PermissionName::DecideRequests);
    }

    public function delete(User $user, RequestAttachment $attachment): bool
    {
        return $attachment->request->isOwnedBy($user) && $attachment->request->status->isEditableByOwner();
    }
}
