<?php

namespace App\Policies;

use App\Models\SupplierPaymentRun;
use App\Models\User;

class SupplierPaymentRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function view(User $user, SupplierPaymentRun $supplierPaymentRun): bool
    {
        return $user->tenants()->whereKey($supplierPaymentRun->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function update(User $user, SupplierPaymentRun $supplierPaymentRun): bool
    {
        return $user->tenants()->whereKey($supplierPaymentRun->tenant_id)->exists();
    }

    public function delete(User $user, SupplierPaymentRun $supplierPaymentRun): bool
    {
        return $user->tenants()->whereKey($supplierPaymentRun->tenant_id)->exists();
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
