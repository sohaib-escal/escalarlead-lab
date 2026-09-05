<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtmConfiguration extends Model
{
    protected $fillable = [
        'creative_id', 'base_url', 'utm_source', 'utm_medium',
        'utm_campaign', 'utm_content', 'utm_term', 'auto_sync',
    ];

    protected function casts(): array
    {
        return ['auto_sync' => 'boolean'];
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    /**
     * Build the final tracking URL from the base URL + non-empty UTM parameters.
     */
    public function finalUrl(?string $fallbackBaseUrl = null): ?string
    {
        $base = $this->base_url ?: $fallbackBaseUrl;

        if (! $base) {
            return null;
        }

        $params = array_filter([
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'utm_content' => $this->utm_content,
            'utm_term' => $this->utm_term,
        ], fn ($v) => filled($v));

        if ($params === []) {
            return $base;
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.http_build_query($params);
    }
}
