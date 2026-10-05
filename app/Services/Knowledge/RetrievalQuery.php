<?php

namespace App\Services\Knowledge;

/**
 * What the conversation knows at the moment it asks for grounding.
 */
class RetrievalQuery
{
    /**
     * @param  string  $text  the homeowner's last message
     * @param  string|null  $problemFamily  from the conversation state, not from the text
     * @param  array<int, string>  $contentTypes  empty means "any"
     */
    public function __construct(
        public readonly string $text,
        public readonly ?string $problemFamily = null,
        public readonly ?int $productId = null,
        public readonly array $contentTypes = [],
        public readonly ?int $maxPassages = null,
        public readonly ?int $maxTokens = null,
    ) {}

    public function limit(): int
    {
        return $this->maxPassages ?? config('knowledge.retrieval.max_passages');
    }

    public function tokenBudget(): int
    {
        return $this->maxTokens ?? config('knowledge.retrieval.max_tokens');
    }
}
