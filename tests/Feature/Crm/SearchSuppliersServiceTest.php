<?php

namespace Tests\Feature\Crm;

use App\Domains\Crm\Services\SearchSuppliersService;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSuppliersServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_searches_by_name_code_and_email_within_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();

        Supplier::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme Corp', 'code' => 'ACME', 'email' => 'buy@acme.test']);
        Supplier::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Beta Ltd', 'code' => 'BETA', 'email' => 'ops@beta.test']);
        Supplier::factory()->create(['tenant_id' => $other->id, 'name' => 'Acme Corp', 'code' => 'ACME-X']);

        $service = app(SearchSuppliersService::class);

        $this->assertCount(1, $service->execute($tenant->id, 'Acme'));
        $this->assertCount(1, $service->execute($tenant->id, 'BETA'));
        $this->assertCount(1, $service->execute($tenant->id, 'ops@beta'));
    }

    public function test_merges_selected_supplier_when_not_in_search_results(): void
    {
        $tenant = Tenant::factory()->create();

        $selected = Supplier::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Zulu Supply']);
        Supplier::factory()->count(25)->create(['tenant_id' => $tenant->id]);

        $service = app(SearchSuppliersService::class);
        $results = $service->execute($tenant->id, 'nomatch', $selected->id);

        $this->assertTrue($results->contains('id', $selected->id));
    }
}
