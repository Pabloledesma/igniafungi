<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchLoss extends Model
{
    protected $fillable = ['batch_id', 'phase_id', 'quantity', 'reason', 'user_id'];
}
