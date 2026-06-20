<?php

namespace Tests\Feature\Accounting;

use App\Enums\BankAccountStatus;
use App\Enums\BankAccountType;
use App\Livewire\BankAccounts\BankAccountFormModal;
use App\Models\BankAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BankAccountCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_accounts_page_renders_for_tenant_user(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $this->get(route('bank-accounts.index'))->assertOk();
    }

    public function test_user_can_create_update_and_delete_bank_account_via_livewire(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test(BankAccountFormModal::class)
            ->call('prepareCreate')
            ->set('bank_name', 'BDO Unibank')
            ->set('account_number', '1234567890')
            ->set('account_name', 'JMC Trading Corp')
            ->set('account_type', BankAccountType::Checking->value)
            ->set('status', BankAccountStatus::Active->value)
            ->call('saveBankAccount')
            ->assertDispatched('bank-account-saved');

        $bankAccount = BankAccount::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $bankAccount->id,
            'tenant_id' => $tenant->id,
            'bank_name' => 'BDO Unibank',
            'account_number' => '1234567890',
            'account_name' => 'JMC Trading Corp',
            'account_type' => BankAccountType::Checking->value,
            'status' => BankAccountStatus::Active->value,
        ]);

        Livewire::test(BankAccountFormModal::class)
            ->call('openForEdit', $bankAccount->id)
            ->set('bank_name', 'BPI')
            ->set('status', BankAccountStatus::Inactive->value)
            ->call('saveBankAccount')
            ->assertDispatched('bank-account-saved');

        $bankAccount->refresh();
        $this->assertSame('BPI', $bankAccount->bank_name);
        $this->assertSame(BankAccountStatus::Inactive, $bankAccount->status);

        Livewire::test(BankAccountFormModal::class)
            ->call('openForEdit', $bankAccount->id)
            ->call('deleteBankAccount')
            ->assertDispatched('bank-account-saved');

        $this->assertDatabaseMissing('bank_accounts', ['id' => $bankAccount->id]);
    }

    public function test_account_number_must_be_unique_within_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);

        BankAccount::factory()->create([
            'tenant_id' => $tenant->id,
            'account_number' => '9999888877',
        ]);

        BankAccount::factory()->create([
            'tenant_id' => $otherTenant->id,
            'account_number' => '9999888877',
        ]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test(BankAccountFormModal::class)
            ->call('prepareCreate')
            ->set('bank_name', 'Metrobank')
            ->set('account_number', '9999888877')
            ->set('account_name', 'Duplicate Test')
            ->set('account_type', BankAccountType::Savings->value)
            ->set('status', BankAccountStatus::Active->value)
            ->call('saveBankAccount')
            ->assertHasErrors(['account_number']);
    }
}
