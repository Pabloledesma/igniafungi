<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Harvest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WeightHandbrakeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function batch_throws_exception_if_bag_weight_is_suspiciously_high()
    {
        $this->expectException(ValidationException::class);

        Batch::factory()->create([
            'bag_weight' => 26 // Limit is 25
        ]);
    }

    /** @test */
    public function harvest_throws_exception_if_weight_is_suspiciously_high()
    {
        $user = User::factory()->create();
        $batch = Batch::factory()->create(['user_id' => $user->id]);

        $this->expectException(ValidationException::class);

        Harvest::create([
            'batch_id' => $batch->id,
            'weight' => 5.1, // Limit is 5
            'harvest_date' => now(),
            'user_id' => $user->id
        ]);
    }
}
