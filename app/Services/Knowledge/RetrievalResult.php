<?php

namespace App\Services\Knowledge;

/**
 * The grounding for one turn, plus enough detail to explain in the admin why
 * the agent said what it said.
 */
class RetrievalResult
{
    /**
     * @param  array<int, RetrievedPassage>  $passages
     */
    public function __construct(
        public readonly array $passages,
        public readonly string $strategy, // fts | trigram | family | none
        public readonly int $tokens,
        public readonly ?string $family = null,
    ) {}

    public static function empty(?string $family = null): self
    {
        return new self([], 'none', 0, $family);
    }

    public function isEmpty(): bool
    {
        return $this->passages === [];
    }

    /**
     * The block that goes into the prompt. Grounding, not a script — the
     * instruction to paraphrase lives with it so it cannot be separated.
     */
    public function toPromptBlock(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $blocks = array_map(function (RetrievedPassage $retrieved) {
            $passage = $retrieved->passage;
            $text = "### {$passage->title}\n{$passage->body}";

            if (filled($passage->never_claim)) {
                $text .= "\n(Interdit : {$passage->never_claim})";
            }

            return $text;
        }, $this->passages);

        return "CONNAISSANCES UTILES (à reformuler avec vos mots, jamais à réciter) :\n\n"
            .implode("\n\n", $blocks);
    }

    /**
     * @return array<string, mixed>
     */
    public function toLog(): array
    {
        return [
            'strategy' => $this->strategy,
            'family' => $this->family,
            'tokens' => $this->tokens,
            'passages' => array_map(fn (RetrievedPassage $r) => [
                'slug' => $r->passage->slug,
                'score' => round($r->score, 4),
                'matched_by' => $r->matchedBy,
            ], $this->passages),
        ];
    }
}
