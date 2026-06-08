<?php

namespace App\Domains\Inventory\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class SearchProductsService
{
    /**
     * @return Collection<int, Product>
     */
    public function execute(int $tenantId, string $term, ?int $selectedId = null, int $limit = 20): Collection
    {
        $term = trim($term);

        $results = Product::query()
            ->where('tenant_id', $tenantId)
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', '%'.$term.'%')
                        ->orWhere('sku', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'sku']);

        if ($selectedId !== null && ! $results->contains('id', $selectedId)) {
            $selected = Product::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($selectedId)
                ->first(['id', 'name', 'sku']);

            if ($selected !== null) {
                $results = $results->prepend($selected);
            }
        }

        return $results;
    }
}
