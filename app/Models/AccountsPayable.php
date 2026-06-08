<?php

namespace App\Models;

use App\Enums\AccountingOpenItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountsPayable extends Model
{
    protected $table = 'accounts_payable';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'goods_receipt_id',
        'supplier_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'payment_terms_days',
        'priority',
        'total_amount',
        'amount_paid',
        'status',
        'posted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:4',
            'amount_paid' => 'decimal:4',
            'status' => AccountingOpenItemStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'payment_terms_days' => 'integer',
            'priority' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<SupplierPaymentAllocation, $this>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'accounts_payable_id');
    }

    /**
     * @return HasMany<SupplierPaymentRunItem, $this>
     */
    public function paymentRunItems(): HasMany
    {
        return $this->hasMany(SupplierPaymentRunItem::class, 'accounts_payable_id');
    }

    /**
     * @return HasMany<SupplierAdvanceApplication, $this>
     */
    public function advanceApplications(): HasMany
    {
        return $this->hasMany(SupplierAdvanceApplication::class);
    }
}
