<?php

namespace App\Services;

use App\Models\Creative;
use Illuminate\Support\Facades\Auth;

class HistoryLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(Creative $creative, string $event, string $description, array $meta = []): void
    {
        $creative->history()->create([
            'user_id' => Auth::id(),
            'event' => $event,
            'description' => $description,
            'meta' => $meta ?: null,
        ]);
    }
}
