<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Harvest extends Model
{
    /** @use HasFactory<\Database\Factories\HarvestFactory> */
    use HasFactory;

    protected $fillable = ['batch_id', 'weight']; 

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
