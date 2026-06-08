<?php

namespace App\Domains\Crm\Services;

use App\Models\Supplier;
use Illuminate\Support\Collection;

class SearchSuppliersService
{
    /**
     * @return Collection<int, Supplier>
     */
    public function execute(int $tenantId, string $term, ?int $selectedId = null, int $limit = 20): Collection
    {
        $term = trim($term);

        $results = Supplier::query()
            ->where('tenant_id', $tenantId)
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', '%'.$term.'%')
                        ->orWhere('code', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'code', 'email']);

        if ($selectedId !== null && ! $results->contains('id', $selectedId)) {
            $selected = Supplier::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($selectedId)
                ->first(['id', 'name', 'code', 'email']);

            if ($selected !== null) {
                $results = $results->prepend($selected);
            }
        }

        return $results;
    }
}
