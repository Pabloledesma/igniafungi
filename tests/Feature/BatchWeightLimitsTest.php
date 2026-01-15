<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BatchWeightLimitsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function batch_cannot_exceed_50kg_dry_weight()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Error de capacidad'); // Check partial message

        Batch::factory()->create([
            'weigth_dry' => 51
        ]);
    }

    /** @test */
    public function batch_allows_50kg_dry_weight()
    {
        $batch = Batch::factory()->create([
            'weigth_dry' => 50
        ]);

        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'weigth_dry' => 50]);
    }

    /** @test */
    public function batch_cannot_exceed_25kg_bag_weight()
    {
        $this->expectException(ValidationException::class);

        Batch::factory()->create([
            'bag_weight' => 26
        ]);
    }
}
