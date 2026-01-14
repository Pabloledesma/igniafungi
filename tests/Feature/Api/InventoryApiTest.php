<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Phase;
use App\Models\Strain;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function public_availability_endpoint_is_accessible_without_auth()
    {
        $response = $this->getJson('/api/public/availability');

        $response->assertStatus(200);
    }

    /** @test */
    public function public_availability_returns_sanitized_data()
    {
        // Setup
        $strain = Strain::factory()->create(['name' => 'Golden Teacher', 'incubation_days' => 15]);

        // Active Batch
        $batch = Batch::factory()->create(['strain_id' => $strain->id, 'quantity' => 10, 'status' => 'incubation']);

        // Phase logic for harvest date
        $phase = Phase::factory()->create(['name' => 'Incubación']);
        $batch->phases()->attach($phase->id, [
            'started_at' => now(),
            'finished_at' => null,
            'user_id' => User::factory()->create()->id
        ]);

        $response = $this->getJson('/api/public/availability');

        $response->assertStatus(200)
            ->assertJsonFragment([
                    'name' => 'Golden Teacher',
                    'has_stock' => true,
                ])
            ->assertJsonStructure([
                    '*' => ['name', 'has_stock', 'next_harvest_date']
                ]);

        // Ensure sensitive fields are missing
        $data = $response->json()[0];
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('available_stock', $data); // Exact quantity hidden
        $this->assertArrayNotHasKey('incubation_days', $data);
    }

    /** @test */
    public function protected_inventory_endpoint_requires_authentication()
    {
        $response = $this->getJson('/api/inventory');
        $response->assertStatus(401);
    }

    /** @test */
    public function protected_inventory_endpoint_is_accessible_with_token()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/inventory');
        $response->assertStatus(200);
    }

    /** @test */
    public function get_next_harvest_date_logic_is_correct()
    {
        Carbon::setTestNow('2026-06-01');

        $strain = Strain::factory()->create(['incubation_days' => 10]);
        $phase = Phase::factory()->create(['name' => 'Incubación']);

        // Batch 1: Started today (Jun 1) + 10 days = Harvest Jun 11
        $batch1 = Batch::factory()->create(['strain_id' => $strain->id, 'status' => 'incubation']);
        $batch1->phases()->attach($phase, ['started_at' => now(), 'finished_at' => null, 'user_id' => User::factory()->create()->id]);

        // Batch 2: Started 5 days ago (May 27) + 10 days = Harvest Jun 6
        $batch2 = Batch::factory()->create(['strain_id' => $strain->id, 'status' => 'incubation']);
        $batch2->phases()->attach($phase, ['started_at' => now()->subDays(5), 'finished_at' => null, 'user_id' => User::factory()->create()->id]);

        // Service Test via API or direct
        // Let's test via public API since it exposes this logic
        $response = $this->getJson('/api/public/availability');

        $data = collect($response->json())->firstWhere('name', $strain->name);

        // Expecting Jun 6 (earliest)
        $this->assertEquals('2026-06-06', $data['next_harvest_date']);
    }
}
