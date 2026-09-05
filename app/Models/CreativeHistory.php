<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeHistory extends Model
{
    protected $table = 'creative_history';

    protected $fillable = ['creative_id', 'user_id', 'event', 'description', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
