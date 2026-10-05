<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Conversation extends Model
{
    protected $fillable = [
        'channel', 'external_id', 'status', 'locale', 'problem_family', 'summary',
        'consent_at', 'creative_id', 'campaign_id', 'referral', 'last_inbound_at', 'last_outbound_at',
    ];

    protected function casts(): array
    {
        return [
            'referral' => 'array',
            'consent_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('id');
    }

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * The last few turns, verbatim. Everything older is covered by the summary —
     * sending the whole history on every turn is the easiest way to make this
     * expensive for nothing.
     *
     * @return Collection<int, ConversationMessage>
     */
    public function recentMessages(int $limit = 8)
    {
        return $this->messages()->latest('id')->limit($limit)->get()->reverse()->values();
    }
}
