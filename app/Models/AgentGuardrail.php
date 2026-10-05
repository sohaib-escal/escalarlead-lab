<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fragment of the agent's standing instructions — persona, tone, the
 * never-claim list, the AI disclosure line.
 *
 * Deliberately NOT part of retrieval: these are assembled into the system
 * prompt on every turn. A compliance rule that is only sometimes retrieved is
 * worse than no rule at all.
 */
class AgentGuardrail extends Model
{
    public const KINDS = [
        'persona' => 'Personnage',
        'tone' => 'Ton',
        'rule' => 'Règle',
        'never_claim' => 'Ne jamais affirmer',
        'disclosure' => 'Transparence',
    ];

    protected $fillable = ['slug', 'name', 'kind', 'body', 'is_active', 'position', 'updated_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
