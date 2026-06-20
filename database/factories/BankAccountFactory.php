<?php

namespace Database\Factories;

use App\Enums\BankAccountStatus;
use App\Enums\BankAccountType;
use App\Models\BankAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'bank_name' => fake()->company().' Bank',
            'account_number' => fake()->numerify('##########'),
            'account_name' => fake()->company(),
            'account_type' => fake()->randomElement(BankAccountType::cases()),
            'status' => BankAccountStatus::Active,
        ];
    }
}
