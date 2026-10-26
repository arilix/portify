<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_checkout_upgrades_a_free_user_and_records_the_transaction(): void
    {
        config(['midtrans.mode' => 'demo']);

        $user = User::factory()->create([
            'account_type' => 'free',
            'subscription_type' => 'free',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('upgrade.checkout'), ['plan' => 'pro_monthly']);

        $response->assertRedirect(route('upgrade.finish'));

        $user->refresh();

        $this->assertSame('pro', $user->account_type);
        $this->assertSame('pro', $user->subscription_type);
        $this->assertNotNull($user->subscription_started_at);
        $this->assertNotNull($user->subscription_expired_at);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'subscription_plan' => 'pro_monthly',
            'status' => 'success',
            'payment_type' => 'demo',
        ]);
    }
}
