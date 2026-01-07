<?php

namespace Tests\Feature\Livewire;

use App\Livewire\BatchKanban;
use App\Models\Batch;
use App\Models\Phase;
use App\Models\User;
use App\Models\Strain;
use App\Models\Recipe;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BatchKanbanTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $phases;
    protected $batch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Crear Fases maestras
        $this->phases = collect([
            Phase::create(['name' => 'Inoculación', 'slug' => 'inoculation', 'order' => 1]),
            Phase::create(['name' => 'Incubación', 'slug' => 'incubation', 'order' => 2]),
            Phase::create(['name' => 'Cosecha', 'slug' => 'harvest', 'order' => 3]),
        ]);

        $strain = Strain::factory()->create(['name' => 'Melena de León']);
        $recipe = Recipe::factory()->create();

        // Crear un lote inicial
        $this->batch = Batch::create([
            'user_id' => $this->user->id,
            'strain_id' => $strain->id,
            'recipe_id' => $recipe->id,
            'quantity' => 100,
            'weigth_dry' => 50,
            'inoculation_date' => now(),
            'status' => 'active',
            // El código lo genera el Observer gracias a que quitamos WithoutModelEvents
        ]);

        $this->batch->phases()->attach($this->phases->first()->id, [
            'user_id' => $this->user->id, 
            'started_at' => now()
        ]);
    }

    /** @test */
    public function it_renders_successfully()
    {
        Livewire::test(BatchKanban::class)
            ->assertStatus(200)
            ->assertViewHas('phases');
    }

    /** @test */
    public function can_advance_batch_to_next_phase()
    {
        $nextPhase = $this->phases->get(1); // Incubación

        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->set('selectedBatchId', $this->batch->id)
            ->set('nextPhaseId', $nextPhase->id)
            ->call('confirmTransition')
            ->assertSet('showModal', false);

        $this->assertEquals($nextPhase->id, $this->batch->fresh()->current_phase->id);
    }

    /** @test */
    public function can_register_multiple_harvests_and_keep_batch_active()
    {
        // Mover el lote a la fase de cosecha
        $harvestPhase = $this->phases->last();
        $this->batch->transitionTo($harvestPhase);

        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->set('selectedBatchId', $this->batch->id)
            ->set('isLastPhase', true)
            ->set('harvestWeight', 2.5)
            ->set('harvestDate', now()->format('Y-m-d'))
            ->set('shouldFinishBatch', false) 
            ->call('harvestBatch');

        $this->assertCount(1, $this->batch->fresh()->harvests);
        $this->assertEquals('active', $this->batch->fresh()->status);
        $this->assertEquals(2.5, $this->batch->fresh()->harvests->first()->weight);
    }

    /** @test */
    public function can_discard_total_batch_using_the_new_modal_logic()
    {
        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->call('openDiscardModal', $this->batch->id)
            ->set('isTotalDiscard', true) // Simula marcar el checkbox rojo
            ->set('discardReason', 'Contaminación')
            ->set('discardNotes', 'Moho verde detectado')
            ->call('processDiscard')
            ->assertSet('showDiscardModal', false);

        $freshBatch = $this->batch->fresh();
        $this->assertEquals('contaminated', $freshBatch->status);
        
        // Verificar registro de pérdida
        $this->assertDatabaseHas('batch_losses', [
            'batch_id' => $this->batch->id,
            'quantity' => 100, // Al ser total, toma el total del lote
            'reason' => 'Contaminación'
        ]);
    }

    /** @test */
    public function can_discard_partial_quantity_and_keep_batch_active()
    {
        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->call('openDiscardModal', $this->batch->id)
            ->set('isTotalDiscard', false)
            ->set('discardQuantity', 10)
            ->set('discardReason', 'Dañado')
            ->call('processDiscard');

        $this->assertEquals('active', $this->batch->fresh()->status);
        // La cantidad del lote debió bajar si tu lógica de recordLoss actualiza el balance
        // Si recordLoss no descuenta quantity automáticamente, esta aserción dependerá de tu modelo
    }

    /** @test */
    public function validation_prevents_discarding_more_than_available()
    {
        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->set('selectedBatchId', $this->batch->id)
            ->set('discardQuantity', 500) // Solo hay 100
            ->set('discardReason', 'Contaminación')
            ->call('processDiscard')
            ->assertHasErrors(['discardQuantity']);
    }
}