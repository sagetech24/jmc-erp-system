<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->tenants()->whereKey($purchaseOrder->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->tenants()->whereKey($purchaseOrder->tenant_id)->exists();
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->tenants()->whereKey($purchaseOrder->tenant_id)->exists();
    }

    public function print(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $purchaseOrder->status->isPrintable()) {
            return false;
        }

        return $this->isTenantAdmin($user, $purchaseOrder->tenant_id);
    }

    private function isTenantAdmin(User $user, int $tenantId): bool
    {
        return $user->tenants()
            ->whereKey($tenantId)
            ->wherePivot('role', 'owner')
            ->exists();
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
