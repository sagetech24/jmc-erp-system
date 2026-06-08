<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Services\ClearSupplierAdvancePdcService;
use App\Domains\Accounting\Services\PostAccountsPayableFromGoodsReceiptService;
use App\Domains\Accounting\Services\RecordSupplierAdvanceService;
use App\Domains\Procurement\Services\ClosePurchaseOrderService;
use App\Domains\Procurement\Services\PostGoodsReceiptService;
use App\Enums\AccountingOpenItemStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierAdvanceStatus;
use App\Enums\SupplierPaymentMethod;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierAdvanceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createPurchaseOrder(Tenant $tenant, Supplier $supplier, Product $product): PurchaseOrder
    {
        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'reference_code' => 'PO-ADV-001',
            'rfq_id' => null,
            'status' => PurchaseOrderStatus::Confirmed,
            'order_date' => now()->toDateString(),
            'notes' => null,
        ]);

        $po->lines()->create([
            'product_id' => $product->id,
            'quantity_ordered' => '10.0000',
            'unit_cost' => '2.5000',
            'position' => 0,
        ]);

        return $po;
    }

    public function test_cash_advance_applies_automatically_when_ap_is_posted(): void
    {
        $tenant = Tenant::factory()->create();
        $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $po = $this->createPurchaseOrder($tenant, $supplier, $product);
        $line = $po->lines()->firstOrFail();

        $advance = app(RecordSupplierAdvanceService::class)->execute(
            $tenant->id,
            $po->id,
            '25.0000',
            SupplierPaymentMethod::Cash->value,
            now()->toDateTimeString(),
            'ADV-1',
            null,
            null,
            null,
            null,
            null,
            null,
        );

        $this->assertSame(SupplierAdvanceStatus::Cleared, $advance->status);
        $this->assertSame('25.0000', (string) $advance->amount);

        $receipt = app(PostGoodsReceiptService::class)->execute(
            $tenant->id,
            $po->id,
            [
                ['purchase_order_line_id' => $line->id, 'quantity_received' => '10'],
            ],
            now()->toDateTimeString(),
            'BILL-ADV-1',
            null,
        );

        $ap = app(PostAccountsPayableFromGoodsReceiptService::class)->execute($tenant->id, $receipt->id);

        $this->assertSame('25.0000', (string) $ap->amount_paid);
        $this->assertSame(AccountingOpenItemStatus::Paid, $ap->status);

        $advance->refresh();
        $this->assertSame('25.0000', (string) $advance->amount_applied);
        $this->assertTrue($advance->isFullyApplied());
        $this->assertDatabaseHas('supplier_advance_applications', [
            'supplier_advance_id' => $advance->id,
            'accounts_payable_id' => $ap->id,
            'amount' => '25.0000',
        ]);
    }

    public function test_future_pdc_advance_stays_scheduled_until_cleared(): void
    {
        $tenant = Tenant::factory()->create();
        $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $po = $this->createPurchaseOrder($tenant, $supplier, $product);
        $line = $po->lines()->firstOrFail();

        $advance = app(RecordSupplierAdvanceService::class)->execute(
            $tenant->id,
            $po->id,
            '25.0000',
            SupplierPaymentMethod::Pdc->value,
            now()->toDateTimeString(),
            'PDC-100',
            null,
            'PDC-100',
            now()->addWeek()->toDateString(),
            'Test Bank',
            'Supplier Co',
            null,
        );

        $this->assertSame(SupplierAdvanceStatus::Scheduled, $advance->status);

        $receipt = app(PostGoodsReceiptService::class)->execute(
            $tenant->id,
            $po->id,
            [
                ['purchase_order_line_id' => $line->id, 'quantity_received' => '10'],
            ],
            now()->toDateTimeString(),
            'BILL-PDC-1',
            null,
        );

        $ap = app(PostAccountsPayableFromGoodsReceiptService::class)->execute($tenant->id, $receipt->id);

        $this->assertSame('0.0000', (string) $ap->amount_paid);
        $this->assertSame(AccountingOpenItemStatus::Open, $ap->status);

        $cleared = app(ClearSupplierAdvancePdcService::class)->execute($tenant->id, $advance->id);
        $this->assertSame(SupplierAdvanceStatus::Cleared, $cleared->status);

        $ap->refresh();
        $this->assertSame('25.0000', (string) $ap->amount_paid);
        $this->assertSame(AccountingOpenItemStatus::Paid, $ap->status);
    }

    public function test_advance_cannot_exceed_purchase_order_total(): void
    {
        $tenant = Tenant::factory()->create();
        $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $po = $this->createPurchaseOrder($tenant, $supplier, $product);

        $this->expectException(\InvalidArgumentException::class);

        app(RecordSupplierAdvanceService::class)->execute(
            $tenant->id,
            $po->id,
            '25.0001',
            SupplierPaymentMethod::Cash->value,
            now()->toDateTimeString(),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
    }

    public function test_cannot_close_purchase_order_with_unapplied_advance(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $po = $this->createPurchaseOrder($tenant, $supplier, $product);

        app(RecordSupplierAdvanceService::class)->execute(
            $tenant->id,
            $po->id,
            '10.0000',
            SupplierPaymentMethod::Cash->value,
            now()->toDateTimeString(),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );

        $this->expectException(\InvalidArgumentException::class);

        app(ClosePurchaseOrderService::class)->execute(
            $tenant->id,
            $po->id,
            $user->id,
            'Supplier cancelled remaining items.',
        );
    }

    public function test_partial_advance_leaves_remaining_balance_on_payable(): void
    {
        $tenant = Tenant::factory()->create();
        $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $po = $this->createPurchaseOrder($tenant, $supplier, $product);
        $line = $po->lines()->firstOrFail();

        app(RecordSupplierAdvanceService::class)->execute(
            $tenant->id,
            $po->id,
            '10.0000',
            SupplierPaymentMethod::Cash->value,
            now()->toDateTimeString(),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );

        $receipt = app(PostGoodsReceiptService::class)->execute(
            $tenant->id,
            $po->id,
            [
                ['purchase_order_line_id' => $line->id, 'quantity_received' => '10'],
            ],
            now()->toDateTimeString(),
            'BILL-PARTIAL',
            null,
        );

        $ap = app(PostAccountsPayableFromGoodsReceiptService::class)->execute($tenant->id, $receipt->id);

        $this->assertSame('10.0000', (string) $ap->amount_paid);
        $this->assertSame(AccountingOpenItemStatus::Partial, $ap->status);
    }
}
