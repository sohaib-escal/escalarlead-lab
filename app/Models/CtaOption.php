<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtaOption extends Model
{
    protected $fillable = ['label', 'slug', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
