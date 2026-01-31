<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'summary',
        'content',
        'image',
        'is_published',
        'user_id',
        'meta_description',
        'product_id'
    ];

    protected static function booted()
    {
        static::creating(function ($post) {
            if (auth()->check()) {
                $post->user_id = auth()->id();
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
