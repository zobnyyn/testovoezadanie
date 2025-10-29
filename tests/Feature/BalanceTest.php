<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Balance;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Создаем тестовых пользователей
        User::factory()->create(['id' => 1, 'email' => 'user1@test.com']);
        User::factory()->create(['id' => 2, 'email' => 'user2@test.com']);
    }

    public function test_deposit_creates_balance_for_new_user(): void
    {
        $response = $this->postJson('/api/deposit', [
            'user_id' => 1,
            'amount' => 500.00,
            'comment' => 'Пополнение через карту',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'user_id' => 1,
                'amount' => 500.00,
                'balance' => 500.00,
            ]);

        $this->assertDatabaseHas('balances', [
            'user_id' => 1,
            'balance' => 500.00,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => 1,
            'type' => 'deposit',
            'amount' => 500.00,
        ]);
    }

    public function test_deposit_adds_to_existing_balance(): void
    {
        Balance::create(['user_id' => 1, 'balance' => 100.00]);

        $response = $this->postJson('/api/deposit', [
            'user_id' => 1,
            'amount' => 200.00,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'balance' => 300.00,
            ]);
    }

    public function test_deposit_validates_required_fields(): void
    {
        $response = $this->postJson('/api/deposit', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['error', 'messages']);
    }

    public function test_deposit_validates_user_exists(): void
    {
        $response = $this->postJson('/api/deposit', [
            'user_id' => 999,
            'amount' => 100.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_withdraw_decreases_balance(): void
    {
        Balance::create(['user_id' => 1, 'balance' => 500.00]);

        $response = $this->postJson('/api/withdraw', [
            'user_id' => 1,
            'amount' => 200.00,
            'comment' => 'Покупка подписки',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'user_id' => 1,
                'amount' => 200.00,
                'balance' => 300.00,
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => 1,
            'type' => 'withdraw',
            'amount' => 200.00,
        ]);
    }

    public function test_withdraw_fails_with_insufficient_balance(): void
    {
        Balance::create(['user_id' => 1, 'balance' => 100.00]);

        $response = $this->postJson('/api/withdraw', [
            'user_id' => 1,
            'amount' => 200.00,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'error' => 'Недостаточно средств на балансе',
            ]);
    }

    public function test_withdraw_fails_when_no_balance(): void
    {
        $response = $this->postJson('/api/withdraw', [
            'user_id' => 1,
            'amount' => 100.00,
        ]);

        $response->assertStatus(409);
    }

    public function test_transfer_between_users(): void
    {
        Balance::create(['user_id' => 1, 'balance' => 500.00]);

        $response = $this->postJson('/api/transfer', [
            'from_user_id' => 1,
            'to_user_id' => 2,
            'amount' => 150.00,
            'comment' => 'Перевод другу',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'from_user_id' => 1,
                'to_user_id' => 2,
                'amount' => 150.00,
                'from_balance' => 350.00,
                'to_balance' => 150.00,
            ]);

        $this->assertDatabaseHas('balances', [
            'user_id' => 1,
            'balance' => 350.00,
        ]);

        $this->assertDatabaseHas('balances', [
            'user_id' => 2,
            'balance' => 150.00,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => 1,
            'type' => 'transfer_out',
            'amount' => 150.00,
            'related_user_id' => 2,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => 2,
            'type' => 'transfer_in',
            'amount' => 150.00,
            'related_user_id' => 1,
        ]);
    }

    public function test_transfer_fails_with_insufficient_balance(): void
    {
        Balance::create(['user_id' => 1, 'balance' => 100.00]);

        $response = $this->postJson('/api/transfer', [
            'from_user_id' => 1,
            'to_user_id' => 2,
            'amount' => 200.00,
        ]);

        $response->assertStatus(409);
    }

    public function test_transfer_fails_to_self(): void
    {
        Balance::create(['user_id' => 1, 'balance' => 500.00]);

        $response = $this->postJson('/api/transfer', [
            'from_user_id' => 1,
            'to_user_id' => 1,
            'amount' => 100.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_get_balance_returns_correct_amount(): void
    {
        Balance::create(['user_id' => 1, 'balance' => 350.00]);

        $response = $this->getJson('/api/balance/1');

        $response->assertStatus(200)
            ->assertJson([
                'user_id' => 1,
                'balance' => 350.00,
            ]);
    }

    public function test_get_balance_returns_zero_for_new_user(): void
    {
        $response = $this->getJson('/api/balance/1');

        $response->assertStatus(200)
            ->assertJson([
                'user_id' => 1,
                'balance' => '0.00',
            ]);
    }

    public function test_get_balance_fails_for_nonexistent_user(): void
    {
        $response = $this->getJson('/api/balance/999');

        $response->assertStatus(404);
    }

    public function test_concurrent_operations_maintain_consistency(): void
    {
        Balance::create(['user_id' => 1, 'balance' => 1000.00]);

        // Симулируем несколько одновременных операций
        $this->postJson('/api/withdraw', ['user_id' => 1, 'amount' => 100.00]);
        $this->postJson('/api/withdraw', ['user_id' => 1, 'amount' => 200.00]);
        $this->postJson('/api/deposit', ['user_id' => 1, 'amount' => 50.00]);

        $balance = Balance::where('user_id', 1)->first();
        $this->assertEquals(750.00, $balance->balance);
    }
}

