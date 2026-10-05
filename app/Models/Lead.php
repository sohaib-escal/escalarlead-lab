<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    public const STATUSES = [
        'new' => 'Nouveau',
        'in_progress' => 'En cours',
        'qualified' => 'Qualifié',
        'appointment_requested' => 'RDV demandé',
        'not_qualified' => 'Non qualifié',
        'lost' => 'Perdu',
    ];

    protected $fillable = [
        'conversation_id', 'first_name', 'last_name', 'phone_e164', 'postal_code', 'city', 'department',
        'qualification_status', 'product_id', 'missing_required_fields', 'details', 'raw_notes',
        'summary', 'qualified_at', 'handed_off_at',
    ];

    protected function casts(): array
    {
        return [
            'missing_required_fields' => 'array',
            'details' => 'array',
            'qualified_at' => 'datetime',
            'handed_off_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(LeadParameter::class);
    }

    public function parameterValues(): BelongsToMany
    {
        return $this->belongsToMany(ParameterValue::class, 'lead_parameters');
    }

    public function fullName(): ?string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name !== '' ? $name : null;
    }
}
