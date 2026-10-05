<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgePassage;

class RetrievedPassage
{
    public function __construct(
        public readonly KnowledgePassage $passage,
        public readonly float $score,
        public readonly string $matchedBy, // fts | trigram | family
    ) {}

    public function tokens(): int
    {
        return $this->passage->estimatedTokens();
    }
}
