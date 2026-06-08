<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Services\ApproveSupplierPaymentRunService;
use App\Domains\Accounting\Services\BuildSupplierPaymentRunService;
use App\Domains\Accounting\Services\DeleteSupplierPaymentRunService;
use App\Domains\Accounting\Services\ExecuteSupplierPaymentRunService;
use App\Domains\Accounting\Services\PostAccountsPayableFromGoodsReceiptService;
use App\Domains\Procurement\Services\PostGoodsReceiptService;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierPaymentRunStatus;
use App\Models\AccountsPayable;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPaymentRun;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountsPayableModernizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_run_lifecycle_creates_supplier_payments_and_allocations(): void
    {
        [$user, $tenant, $supplier] = $this->seedTenantContext();
        $ap = $this->createPayable($tenant->id, $supplier->id, '12.5000');

        $run = app(BuildSupplierPaymentRunService::class)->execute(
            $tenant->id,
            $user->id,
            now()->toDateString(),
            null,
            $supplier->id,
            null,
            'Cycle run',
            [$ap->id],
        );

        $this->assertSame(SupplierPaymentRunStatus::Draft, $run->status);

        app(ApproveSupplierPaymentRunService::class)->execute($tenant->id, $run->id, $user->id);
        app(ExecuteSupplierPaymentRunService::class)->execute($tenant->id, $run->id);

        $run->refresh();
        $this->assertSame(SupplierPaymentRunStatus::Completed, $run->status);
        $this->assertDatabaseHas('supplier_payment_run_items', [
            'supplier_payment_run_id' => $run->id,
            'accounts_payable_id' => $ap->id,
            'executed_amount' => '12.5000',
        ]);
        $this->assertDatabaseHas('supplier_payments', [
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'amount' => '12.5000',
            'reference' => $run->reference_code,
        ]);
    }

    public function test_payables_page_overdue_tab_filters_future_due_items(): void
    {
        [$user, $tenant, $supplier] = $this->seedTenantContext();
        $overdue = $this->createPayable($tenant->id, $supplier->id, '5.0000');
        $future = $this->createPayable($tenant->id, $supplier->id, '8.0000');

        $overdue->update(['due_date' => now()->subDay()->toDateString(), 'invoice_number' => 'INV-OVERDUE']);
        $future->update(['due_date' => now()->addDays(14)->toDateString(), 'invoice_number' => 'INV-FUTURE']);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test('pages::accounting.payables.index')
            ->set('activeTab', 'overdue')
            ->assertSee('INV-OVERDUE')
            ->assertDontSee('INV-FUTURE');
    }

    public function test_payment_run_includes_selected_payables_even_when_due_date_is_in_future(): void
    {
        [$user, $tenant, $supplier] = $this->seedTenantContext();
        $ap = $this->createPayable($tenant->id, $supplier->id, '10.0000');
        $ap->update(['due_date' => now()->addDays(30)->toDateString()]);

        $run = app(BuildSupplierPaymentRunService::class)->execute(
            $tenant->id,
            $user->id,
            now()->toDateString(),
            null,
            null,
            now()->toDateString(),
            null,
            [$ap->id],
        );

        $this->assertCount(1, $run->items);
        $this->assertSame('10.0000', (string) $run->proposed_amount);
    }

    public function test_payment_run_due_date_filter_excludes_future_dated_payables_when_unselected(): void
    {
        [$user, $tenant, $supplier] = $this->seedTenantContext();
        $ap = $this->createPayable($tenant->id, $supplier->id, '10.0000');
        $ap->update(['due_date' => now()->addDays(30)->toDateString()]);

        $this->expectException(\InvalidArgumentException::class);

        app(BuildSupplierPaymentRunService::class)->execute(
            $tenant->id,
            $user->id,
            now()->toDateString(),
            null,
            null,
            now()->toDateString(),
            null,
            [],
        );
    }

    public function test_draft_payment_run_can_be_deleted(): void
    {
        [$user, $tenant, $supplier] = $this->seedTenantContext();
        $run = SupplierPaymentRun::query()->create([
            'tenant_id' => $tenant->id,
            'reference_code' => 'PR-DELETE-001',
            'status' => SupplierPaymentRunStatus::Draft,
            'scheduled_for' => now()->toDateString(),
            'proposed_amount' => '0',
            'approved_amount' => '0',
            'executed_amount' => '0',
            'created_by' => $user->id,
        ]);

        app(DeleteSupplierPaymentRunService::class)->execute($tenant->id, $run->id);

        $this->assertDatabaseMissing('supplier_payment_runs', ['id' => $run->id]);
    }

    public function test_completed_payment_run_cannot_be_deleted(): void
    {
        [$user, $tenant, $supplier] = $this->seedTenantContext();
        $ap = $this->createPayable($tenant->id, $supplier->id, '7.0000');

        $run = app(BuildSupplierPaymentRunService::class)->execute(
            $tenant->id,
            $user->id,
            now()->toDateString(),
            null,
            $supplier->id,
            null,
            null,
            [$ap->id],
        );

        app(ApproveSupplierPaymentRunService::class)->execute($tenant->id, $run->id, $user->id);
        app(ExecuteSupplierPaymentRunService::class)->execute($tenant->id, $run->id);

        $this->expectException(\InvalidArgumentException::class);

        app(DeleteSupplierPaymentRunService::class)->execute($tenant->id, $run->id);
    }

    public function test_payment_run_show_page_can_delete_draft_run(): void
    {
        [$user, $tenant] = $this->seedTenantContext();
        $run = SupplierPaymentRun::query()->create([
            'tenant_id' => $tenant->id,
            'reference_code' => 'PR-DELETE-UI',
            'status' => SupplierPaymentRunStatus::Draft,
            'scheduled_for' => now()->toDateString(),
            'proposed_amount' => '0',
            'approved_amount' => '0',
            'executed_amount' => '0',
            'created_by' => $user->id,
        ]);

        Livewire::test('pages::accounting.payment-runs.show', ['id' => $run->id])
            ->call('deleteRun')
            ->assertRedirect(route('accounting.payment-runs.index'));

        $this->assertDatabaseMissing('supplier_payment_runs', ['id' => $run->id]);
    }

    public function test_payment_run_pages_render_for_tenant_user(): void
    {
        [$user, $tenant, $supplier] = $this->seedTenantContext();
        $ap = $this->createPayable($tenant->id, $supplier->id, '9.0000');
        $run = SupplierPaymentRun::query()->create([
            'tenant_id' => $tenant->id,
            'reference_code' => 'PR-TEST-001',
            'status' => SupplierPaymentRunStatus::Draft,
            'scheduled_for' => now()->toDateString(),
            'proposed_amount' => '9.0000',
            'approved_amount' => '0',
            'executed_amount' => '0',
            'created_by' => $user->id,
        ]);
        $run->items()->create([
            'tenant_id' => $tenant->id,
            'accounts_payable_id' => $ap->id,
            'supplier_id' => $supplier->id,
            'planned_amount' => '9.0000',
            'executed_amount' => '0',
        ]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $this->get(route('accounting.payment-runs.index'))->assertOk();
        $this->get(route('accounting.payment-runs.show', ['id' => $run->id]))->assertOk();
    }

    /**
     * @return array{User, Tenant, Supplier}
     */
    private function seedTenantContext(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        return [$user, $tenant, $supplier];
    }

    private function createPayable(int $tenantId, int $supplierId, string $qty): AccountsPayable
    {
        $product = Product::factory()->create(['tenant_id' => $tenantId]);
        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenantId,
            'supplier_id' => $supplierId,
            'reference_code' => 'PO-'.str()->upper(str()->random(6)),
            'rfq_id' => null,
            'status' => PurchaseOrderStatus::Confirmed,
            'order_date' => now()->toDateString(),
            'notes' => null,
        ]);
        $line = $po->lines()->create([
            'product_id' => $product->id,
            'quantity_ordered' => $qty,
            'unit_cost' => '1.0000',
            'position' => 0,
        ]);
        $receipt = app(PostGoodsReceiptService::class)->execute(
            $tenantId,
            $po->id,
            [['purchase_order_line_id' => $line->id, 'quantity_received' => $qty]],
            now()->toDateTimeString(),
            'INV-'.str()->upper(str()->random(4)),
            null,
        );

        return app(PostAccountsPayableFromGoodsReceiptService::class)->execute($tenantId, $receipt->id);
    }
}
