<?php

namespace App\Policies;

use App\Models\AiGeneration;
use App\Models\User;

class AiGenerationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTenant() || $user->isUser();
    }

    public function create(User $user): bool
    {
        return $user->isTenant() || $user->isUser();
    }

    public function view(User $user, AiGeneration $generation): bool
    {
        $tenantId = $user->tenantId();

        return $tenantId !== null && $generation->tenant_id === $tenantId;
    }
}
