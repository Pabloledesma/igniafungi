<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_has_review_url_attribute()
    {
        $order = new Order();
        $this->assertEquals('https://g.page/r/CeaSqLtP62KVEBI/review', $order->review_url);
    }

    public function test_delivered_status_logs_review_request()
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'entregada. Solicitando reseña:');
            });

        $order = Order::factory()->create(['status' => 'processing']);

        $order->update(['status' => 'delivered']);
    }

    public function test_other_status_does_not_log_review_request()
    {
        Log::shouldReceive('info')->never();

        $order = Order::factory()->create(['status' => 'processing']);

        $order->update(['status' => 'shipped']);
    }
}
