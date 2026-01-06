<?php

namespace App\Models;

use App\Models\BatchLoss;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_batch_id', 
        'strain_id', 
        'recipe_id', 
        'user_id', 
        'code', 
        'weigth_dry',
        'inoculation_date', 
        'quantity',
        'contaminated_quantity',
        'bag_weight',
        'type',
        'grain_type',
        'container_type',
        'status',
        'observations'
    ];

    protected $casts = [
        'inoculation_date' => 'date', // Esto convierte el texto en Objeto Fecha
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function strain() : BelongsTo
    {
        return $this->belongsTo(Strain::class);
    }

    // Relación: Un lote tiene muchas cosechas
    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    public function user() 
    { 
        return $this->belongsTo(User::class); 
    }

    // Relación hacia el lote original (Padre)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'parent_batch_id');
    }

    // Relación hacia los lotes derivados (Hijos/Fructificaciones)
    public function children(): HasMany
    {
        return $this->hasMany(Batch::class, 'parent_batch_id');
    }

    public function phases(): BelongsToMany
    {
        return $this->belongsToMany(Phase::class, 'batch_phases')
            ->withPivot(['id', 'user_id', 'started_at', 'finished_at', 'notes'])
            ->withTimestamps();
    }

    // Relación para las mermas (Uno a Muchos)
    public function losses(): HasMany
    {
        return $this->hasMany(BatchLoss::class);
    }

    // Accessor útil para obtener la fase activa en el test
    public function getCurrentPhaseAttribute()
    {
        return $this->phases()->wherePivot('finished_at', null)->first();
    }

    protected function biologicalEfficiency(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Si el peso seco es 0 o nulo, retornamos 0 para evitar error de división
                if ($this->weigth_dry <= 0) return 0;

                // Sumamos los kilos de todas las cosechas
                $totalHarvest = $this->harvests()->sum('weight');

                // Fórmula: (Total Cosechado / Total Sustrato Seco) * 100
                return round(($totalHarvest / $this->weigth_dry) * 100, 2);
            }
        );
    }

    public function transitionTo(Phase $nextPhase, $notes = null)
    {
        return DB::transaction(function () use ($nextPhase, $notes) {
            // Cerrar fase actual
            $this->phases()->wherePivot('finished_at', null)->updateExistingPivot(
                $this->phases()->wherePivot('finished_at', null)->first()->id,
                ['finished_at' => now()]
            );

            // Abrir nueva fase
            return $this->phases()->attach($nextPhase->id, [
                'user_id' => auth()->id() ?? 1, // Fallback para tests
                'started_at' => now(),
                'notes' => $notes
            ]);
        });
    }

    public function recordLoss($qty, $reason, $userId, $details = null)
    {
        return $this->losses()->create([
            'phase_id' => $this->current_phase->id,
            'quantity' => $qty,
            'reason' => $reason,
            'details' => $details,
            'user_id' => $userId
        ]);
    }

    public function getDaysInCurrentPhaseAttribute()
    {
        $current = $this->phases()->wherePivot('finished_at', null)->first();
        return $current ? now()->diffInDays($current->pivot->started_at) : 0;
    }
}
