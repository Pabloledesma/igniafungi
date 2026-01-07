<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Phase extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'slug', 'order'];
    
    /**
     * Relación con los lotes a través de la tabla pivote
     */
    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'batch_phases')
                    ->withPivot(['user_id', 'started_at', 'finished_at', 'notes'])
                    ->withTimestamps();
    }
}
