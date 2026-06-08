<?php

namespace App\Policies;

use App\Models\SupplierAdvance;
use App\Models\User;

class SupplierAdvancePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function view(User $user, SupplierAdvance $supplierAdvance): bool
    {
        return $user->tenants()->whereKey($supplierAdvance->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function update(User $user, SupplierAdvance $supplierAdvance): bool
    {
        return $user->tenants()->whereKey($supplierAdvance->tenant_id)->exists();
    }

    private function onboardedToCurrentTenant(User $user): bool
    {
        $tenantId = session('current_tenant_id');

        if ($tenantId === null) {
            return false;
        }

        return $user->tenants()->whereKey((int) $tenantId)->exists();
    }
}
