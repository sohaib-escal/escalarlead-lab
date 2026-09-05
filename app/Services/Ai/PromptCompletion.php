<?php

namespace App\Services\Ai;

class PromptCompletion
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $text,
        public array $meta = [],
    ) {}
}
