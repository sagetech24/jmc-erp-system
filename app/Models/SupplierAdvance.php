<?php

namespace App\Models;

use App\Enums\SupplierAdvanceStatus;
use App\Enums\SupplierPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierAdvance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'supplier_id',
        'amount',
        'amount_applied',
        'payment_method',
        'status',
        'paid_at',
        'cleared_at',
        'cheque_number',
        'cheque_date',
        'cheque_bank',
        'cheque_payee',
        'reference',
        'notes',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'amount_applied' => 'decimal:4',
            'payment_method' => SupplierPaymentMethod::class,
            'status' => SupplierAdvanceStatus::class,
            'paid_at' => 'datetime',
            'cleared_at' => 'datetime',
            'cheque_date' => 'date',
        ];
    }

    public function remainingAmount(): string
    {
        $remaining = bcsub((string) $this->amount, (string) $this->amount_applied, 4);

        return bccomp($remaining, '0', 4) === -1 ? '0' : $remaining;
    }

    public function isFullyApplied(): bool
    {
        return bccomp($this->remainingAmount(), '0', 4) !== 1;
    }

    public function isApplicable(): bool
    {
        return $this->status === SupplierAdvanceStatus::Cleared
            && bccomp($this->remainingAmount(), '0', 4) === 1;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<SupplierAdvanceApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(SupplierAdvanceApplication::class);
    }
}
