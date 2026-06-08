<?php

namespace Tests\Feature\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrderPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_print_confirmed_purchase_order(): void
    {
        [$user, $tenant, $po] = $this->seedConfirmedPurchaseOrder();

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $this->get(route('procurement.purchase-orders.print', $po->id))
            ->assertOk()
            ->assertSee($po->reference_code, false)
            ->assertSee($po->supplier->name, false);

        Livewire::test('pages::procurement.purchase-orders.show', ['id' => $po->id])
            ->assertSee(__('Print PO'), false);
    }

    public function test_non_admin_cannot_print_purchase_order(): void
    {
        [$user, $tenant, $po] = $this->seedConfirmedPurchaseOrder(role: 'member');

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $this->get(route('procurement.purchase-orders.print', $po->id))
            ->assertForbidden();

        Livewire::test('pages::procurement.purchase-orders.show', ['id' => $po->id])
            ->assertDontSee(__('Print PO'), false);
    }

    public function test_cancelled_purchase_order_cannot_be_printed(): void
    {
        [$user, $tenant, $po] = $this->seedConfirmedPurchaseOrder();
        $po->update(['status' => PurchaseOrderStatus::Cancelled]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $this->get(route('procurement.purchase-orders.print', $po->id))
            ->assertForbidden();
    }

    public function test_received_purchase_order_cannot_be_printed(): void
    {
        [$user, $tenant, $po] = $this->seedConfirmedPurchaseOrder();
        $po->update(['status' => PurchaseOrderStatus::Received]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $this->get(route('procurement.purchase-orders.print', $po->id))
            ->assertForbidden();

        Livewire::test('pages::procurement.purchase-orders.show', ['id' => $po->id])
            ->assertDontSee(__('Print PO'), false);
    }

    /**
     * @return array{0: User, 1: Tenant, 2: PurchaseOrder}
     */
    private function seedConfirmedPurchaseOrder(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['name' => 'Acme Trading']);
        $user->tenants()->attach($tenant, ['role' => $role]);
        $supplier = Supplier::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Supplies',
        ]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'reference_code' => 'PO-PRINT-1',
            'rfq_id' => null,
            'status' => PurchaseOrderStatus::Confirmed,
            'order_date' => now()->toDateString(),
            'notes' => 'Deliver to main warehouse.',
        ]);

        $po->lines()->create([
            'product_id' => $product->id,
            'quantity_ordered' => '4.0000',
            'unit_cost' => '25.0000',
            'position' => 0,
        ]);

        return [$user, $tenant, $po->fresh(['supplier', 'lines.product'])];
    }
}
