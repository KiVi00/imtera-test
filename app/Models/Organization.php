<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Organization extends Model
{
    protected $fillable = [
        'user_id',
        'url',
        'yandex_id',
        'title',
        'rating',
        'ratings_count',
        'reviews_count',
        'parse_status',
        'parse_error',
        'last_parsed_at',
    ];

    protected $casts = [
        'last_parsed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Review::class);
    }
}
