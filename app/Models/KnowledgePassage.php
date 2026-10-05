<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One retrievable piece of business knowledge: an explanation, an FAQ answer,
 * an objection response. Short, standalone, French, written to be paraphrased
 * by the agent rather than recited.
 */
class KnowledgePassage extends Model
{
    protected $fillable = [
        'slug', 'title', 'body', 'keywords', 'problem_family', 'content_type',
        'product_id', 'audience', 'never_claim', 'source', 'is_active', 'position', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Rough token cost of this passage once it is in a prompt.
     */
    public function estimatedTokens(): int
    {
        $text = $this->title.' '.$this->body;

        return (int) ceil(mb_strlen($text) / config('knowledge.retrieval.chars_per_token'));
    }
}
