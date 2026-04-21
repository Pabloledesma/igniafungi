<?php

namespace Tests\Feature;

use App\Jobs\SyncBatchToSalesforce;
use App\Jobs\SyncHarvestToSalesforce;
use App\Models\Batch;
use App\Models\Harvest;
use App\Models\Phase;
use App\Models\Strain;
use App\Models\User;
use App\Observers\BatchObserver;
use App\Services\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesforceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BatchObserver::clearProcessed();
        Queue::fake();
    }

    // ─── Job Dispatch Tests ───────────────────────────────────────────────────

    #[Test]
    public function creating_a_batch_dispatches_sync_job(): void
    {
        $batch = Batch::factory()->create(['type' => 'grain', 'status' => 'active']);

        Queue::assertPushed(SyncBatchToSalesforce::class, function ($job) use ($batch) {
            return $job->batchId === $batch->id;
        });
    }

    #[Test]
    public function updating_a_batch_dispatches_sync_job(): void
    {
        $batch = Batch::factory()->create(['type' => 'grain', 'status' => 'active']);
        Queue::fake(); // Reset after creation

        $batch->update(['quantity' => $batch->quantity - 1]);

        Queue::assertPushed(SyncBatchToSalesforce::class, function ($job) use ($batch) {
            return $job->batchId === $batch->id;
        });
    }

    #[Test]
    public function creating_a_harvest_dispatches_sync_job(): void
    {
        $phase = Phase::factory()->create();
        $batch = Batch::factory()->create(['type' => 'grain', 'status' => 'active']);
        Queue::fake(); // Reset after batch creation

        $harvest = Harvest::factory()->create([
            'batch_id' => $batch->id,
            'phase_id' => $phase->id,
            'weight' => 0.5,
        ]);

        Queue::assertPushed(SyncHarvestToSalesforce::class, function ($job) use ($harvest) {
            return $job->harvestId === $harvest->id;
        });
    }

    #[Test]
    public function sync_job_is_not_dispatched_for_unrelated_model_events(): void
    {
        Queue::fake();

        // Creating a user should NOT trigger salesforce sync jobs
        User::factory()->create();

        Queue::assertNotPushed(SyncBatchToSalesforce::class);
        Queue::assertNotPushed(SyncHarvestToSalesforce::class);
    }

    // ─── Job Execution Tests ──────────────────────────────────────────────────

    #[Test]
    public function sync_batch_job_calls_salesforce_service_with_correct_data(): void
    {
        $strain = Strain::factory()->create(['name' => 'Melena de León']);
        $batch = Batch::factory()->create([
            'strain_id' => $strain->id,
            'type' => 'grain',
            'status' => 'active',
            'quantity' => 25,
            'initial_wet_weight' => 8.0,
            'inoculation_date' => '2025-04-20',
        ]);

        $mockService = Mockery::mock(SalesforceService::class);
        $mockService->shouldReceive('upsertBatch')
            ->once()
            ->with(Mockery::on(function ($data) use ($batch, $strain) {
                return $data['id'] === $batch->id
                    && $data['strain'] === $strain->name
                    && $data['status'] === 'active'
                    && $data['quantity'] === 25;
            }));

        $this->instance(SalesforceService::class, $mockService);

        Queue::fake([]); // Allow real execution
        (new SyncBatchToSalesforce($batch->id))->handle($mockService);
    }

    #[Test]
    public function sync_harvest_job_calls_salesforce_service_with_correct_data(): void
    {
        $phase = Phase::factory()->create();
        $batch = Batch::factory()->create(['type' => 'grain', 'status' => 'active']);
        $harvest = Harvest::factory()->create([
            'batch_id' => $batch->id,
            'phase_id' => $phase->id,
            'weight' => 0.75,
            'harvest_date' => '2025-04-20',
            'notes' => 'Excelente cosecha',
        ]);

        $mockService = Mockery::mock(SalesforceService::class);
        $mockService->shouldReceive('upsertHarvest')
            ->once()
            ->with(Mockery::on(function ($data) use ($harvest) {
                return $data['id'] === $harvest->id
                    && (float) $data['weight'] === 0.75
                    && $data['batch_id'] === $harvest->batch_id;
            }));

        (new SyncHarvestToSalesforce($harvest->id))->handle($mockService);
    }

    #[Test]
    public function sync_batch_job_retries_on_failure(): void
    {
        $batch = Batch::factory()->create(['type' => 'grain', 'status' => 'active']);

        $job = new SyncBatchToSalesforce($batch->id);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    #[Test]
    public function sync_harvest_job_retries_on_failure(): void
    {
        $phase = Phase::factory()->create();
        $batch = Batch::factory()->create(['type' => 'grain', 'status' => 'active']);
        $harvest = Harvest::factory()->create([
            'batch_id' => $batch->id,
            'phase_id' => $phase->id,
        ]);

        $job = new SyncHarvestToSalesforce($harvest->id);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    #[Test]
    public function sync_batch_job_fails_gracefully_when_batch_does_not_exist(): void
    {
        $mockService = Mockery::mock(SalesforceService::class);
        $mockService->shouldNotReceive('upsertBatch');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        (new SyncBatchToSalesforce(99999))->handle($mockService);
    }

    #[Test]
    public function sync_harvest_job_fails_gracefully_when_harvest_does_not_exist(): void
    {
        $mockService = Mockery::mock(SalesforceService::class);
        $mockService->shouldNotReceive('upsertHarvest');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        (new SyncHarvestToSalesforce(99999))->handle($mockService);
    }
}
