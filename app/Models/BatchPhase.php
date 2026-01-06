<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchPhase extends Model
{
    protected $fillable = ['batch_id', 'phase_id', 'user_id', 'started_at', 'finished_at', 'notes'];
}
