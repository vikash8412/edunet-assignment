<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTenant() || $user->isUser();
    }

    public function create(User $user): bool
    {
        return $user->isTenant() || $user->isUser();
    }

    public function view(User $user, Import $import): bool
    {
        return $this->sameTenant($user, $import);
    }

    public function commit(User $user, Import $import): bool
    {
        return $this->sameTenant($user, $import);
    }

    private function sameTenant(User $user, Import $import): bool
    {
        $tenantId = $user->tenantId();

        return $tenantId !== null && $import->tenant_id === $tenantId;
    }
}
