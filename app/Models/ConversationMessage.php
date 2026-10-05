<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    public const INBOUND = 'inbound';

    public const OUTBOUND = 'outbound';

    protected $fillable = [
        'conversation_id', 'direction', 'external_id', 'type', 'body', 'raw', 'status', 'meta',
    ];

    protected function casts(): array
    {
        return ['raw' => 'array', 'meta' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isInbound(): bool
    {
        return $this->direction === self::INBOUND;
    }
}
