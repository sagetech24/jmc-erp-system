<?php

namespace Tests\Feature\Inventory;

use App\Domains\Inventory\Services\SearchProductsService;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchProductsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_searches_by_name_and_sku_within_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();

        Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Widget Alpha', 'sku' => 'WA-001']);
        Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Gadget Beta', 'sku' => 'GB-002']);
        Product::factory()->create(['tenant_id' => $other->id, 'name' => 'Widget Alpha', 'sku' => 'WA-999']);

        $service = app(SearchProductsService::class);

        $byName = $service->execute($tenant->id, 'Widget');
        $this->assertCount(1, $byName);
        $this->assertSame('Widget Alpha', $byName->first()->name);

        $bySku = $service->execute($tenant->id, 'GB-');
        $this->assertCount(1, $bySku);
        $this->assertSame('Gadget Beta', $bySku->first()->name);
    }

    public function test_merges_selected_product_when_not_in_search_results(): void
    {
        $tenant = Tenant::factory()->create();

        $selected = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Zulu Item', 'sku' => 'Z-001']);
        Product::factory()->count(25)->create(['tenant_id' => $tenant->id]);

        $service = app(SearchProductsService::class);
        $results = $service->execute($tenant->id, 'nomatch', $selected->id);

        $this->assertTrue($results->contains('id', $selected->id));
    }

    public function test_returns_limited_results_when_term_empty(): void
    {
        $tenant = Tenant::factory()->create();
        Product::factory()->count(30)->create(['tenant_id' => $tenant->id]);

        $service = app(SearchProductsService::class);
        $results = $service->execute($tenant->id, '');

        $this->assertCount(20, $results);
    }
}
