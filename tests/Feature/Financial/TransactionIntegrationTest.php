<?php

namespace Tests\Feature\Financial;

use App\Filament\Widgets\BreakEvenWidget;
use App\Models\Batch;
use App\Models\Harvest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Strain;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;

class TransactionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function does_not_create_transaction_when_harvest_is_created()
    {
        // Setup
        $strain = Strain::factory()->create(['name' => 'Golden Teacher']);
        $category = \App\Models\Category::factory()->create();
        $product = Product::factory()->create([
            'strain_id' => $strain->id,
            'price' => 10,
            'category_id' => $category->id
        ]);
        $batch = Batch::factory()->create(['strain_id' => $strain->id, 'code' => 'B-001']);
        $user = User::factory()->create();
        $phase = \App\Models\Phase::factory()->create();

        // Act
        $harvest = Harvest::create([
            'batch_id' => $batch->id,
            'weight' => 50,
            'harvest_date' => now(),
            'user_id' => $user->id,
            'phase_id' => $phase->id,
        ]);

        // Assert
        $this->assertDatabaseEmpty('transactions');
    }

    /** @test */
    public function creates_income_transaction_only_when_order_is_paid()
    {
        // Setup
        $user = User::factory()->create();

        // Act 1: Create Order
        $order = Order::create([
            'user_id' => $user->id,
            'grand_total' => 125.50,
            'status' => 'new',
            'currency' => 'usd',
            'shipping_amount' => 0,
            'payment_status' => 'pending', // Not paid yet
            'payment_method' => 'stripe'
        ]);

        // Assert 1: No transaction yet
        $this->assertDatabaseEmpty('transactions');

        // Act 2: Update to Paid
        $order->update(['payment_status' => 'paid']);

        // Assert 2: Transaction Created
        $this->assertDatabaseHas('transactions', [
            'type' => 'income',
            'amount' => 125.50,
            'category' => 'sales',
            'description' => "Orden #{$order->reference}",
            'reference_id' => $order->id,
            'reference_type' => Order::class,
        ]);
    }

    /** @test */
    public function creates_income_transaction_when_order_is_created_as_paid()
    {
        // Setup
        $user = User::factory()->create();

        // Act
        $order = Order::create([
            'user_id' => $user->id,
            'grand_total' => 250.00,
            'status' => 'new',
            'currency' => 'usd',
            'shipping_amount' => 0,
            'payment_status' => 'paid', // Created as paid
            'payment_method' => 'stripe'
        ]);

        // Assert
        $this->assertDatabaseHas('transactions', [
            'type' => 'income',
            'amount' => 250.00,
            'category' => 'sales',
            'description' => "Orden #{$order->reference}",
            'reference_id' => $order->id,
            'reference_type' => Order::class,
        ]);
    }

    /** @test */
    public function break_even_widget_calculates_correctly()
    {
        // Setup
        Transaction::create([
            'description' => 'Expense 1',
            'amount' => 200,
            'type' => 'expense',
            'category' => 'supplies',
            'date' => now(),
        ]);

        Transaction::create([
            'description' => 'Income 1',
            'amount' => 120, // 60% of 200
            'type' => 'income',
            'category' => 'sales',
            'date' => now(),
        ]);

        // Act
        $component = Livewire::test(BreakEvenWidget::class);

        // Assert
        $component->assertViewHas('expenses', 200.0)
            ->assertViewHas('income', 120.0)
            ->assertViewHas('percentage', 60.0)
            ->assertViewHas('missing', 80.0);
    }
}
