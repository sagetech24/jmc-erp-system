<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAdvanceApplication extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_advance_id',
        'accounts_payable_id',
        'amount',
        'applied_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SupplierAdvance, $this>
     */
    public function supplierAdvance(): BelongsTo
    {
        return $this->belongsTo(SupplierAdvance::class);
    }

    /**
     * @return BelongsTo<AccountsPayable, $this>
     */
    public function accountsPayable(): BelongsTo
    {
        return $this->belongsTo(AccountsPayable::class);
    }
}
