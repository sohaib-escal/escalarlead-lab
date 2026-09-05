<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreativePrompt extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    protected $fillable = [
        'creative_id', 'ai_model_id', 'prompt_template_id', 'version',
        'outcome', 'body', 'status', 'target_format', 'created_by', 'validated_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => 'array',
            'meta' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(CreativeGeneration::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }
}
