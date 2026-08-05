<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;

/**
 * Forms are scoped to their tenant (company). Any user in the same tenant —
 * the tenant owner or any of their team members — shares full access.
 * Registered automatically by Laravel's policy discovery
 * (App\Models\Form -> App\Policies\FormPolicy).
 */
class FormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTenant() || $user->isUser();
    }

    public function create(User $user): bool
    {
        return $user->isTenant() || $user->isUser();
    }

    public function view(User $user, Form $form): bool
    {
        return $this->sameTenant($user, $form);
    }

    public function update(User $user, Form $form): bool
    {
        return $this->sameTenant($user, $form);
    }

    public function delete(User $user, Form $form): bool
    {
        return $this->sameTenant($user, $form);
    }

    private function sameTenant(User $user, Form $form): bool
    {
        $tenantId = $user->tenantId();

        // Explicit null guard: a super's tenantId() is always null and
        // forms.tenant_id is never null, so this makes "a super owns zero
        // forms" a hard invariant rather than an accident of comparison.
        return $tenantId !== null && $form->tenant_id === $tenantId;
    }
}
