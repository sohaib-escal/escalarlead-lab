<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeNote extends Model
{
    protected $fillable = ['creative_id', 'user_id', 'body'];

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
