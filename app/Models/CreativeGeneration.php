<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeGeneration extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** Provider has no public generation API: the admin generates externally and pastes the result back. */
    public const STATUS_AWAITING_MANUAL = 'awaiting_manual';

    protected $fillable = [
        'creative_id', 'creative_prompt_id', 'provider', 'model', 'format', 'status',
        'external_id', 'asset_url', 'asset_reference', 'asset_mime', 'thumbnail_url',
        'error', 'meta', 'created_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'completed_at' => 'datetime'];
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(CreativePrompt::class, 'creative_prompt_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_GENERATING], true);
    }
}
