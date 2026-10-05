<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgePassage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tag-first filtering, then French full-text ranking inside the filtered set.
 *
 * The order matters: the conversation state already knows the problem family,
 * and that single filter does more for relevance than any ranking function.
 * Ranking only decides which of the handful of candidates is best.
 *
 * Three strategies, tried in order:
 *   1. `fts`      — French text search over title/keywords/body, weighted.
 *   2. `trigram`  — similarity on title + keywords, for messages so misspelt
 *                   that stemming finds nothing ("mwasisure", "condansation").
 *   3. `family`   — the curated explanations for the family, so the agent is
 *                   never left ungrounded on a problem we know about.
 *
 * Known limitation. This is lexical matching, so French stems that collide
 * collide here too: « aider » and « aide » both stem to `aid`, which means
 * "est-ce que ça peut aider ?" can surface the state-aid passage. The agent
 * paraphrases rather than recites and the guardrails forbid claiming anything
 * about aid, so the blast radius is small — but this is the class of problem
 * embeddings would solve, and the reason to measure before adding them.
 */
class PostgresKnowledgeRetriever implements KnowledgeRetriever
{
    public function retrieve(RetrievalQuery $query): RetrievalResult
    {
        $text = trim($query->text);

        if ($text === '') {
            return $this->familyFallback($query);
        }

        $tsQuery = $this->tsQueryFor($text);

        $candidates = $tsQuery ? $this->fullText($query, $tsQuery) : collect();
        $strategy = 'fts';

        if ($candidates->isEmpty()) {
            $candidates = $this->trigram($query, $text);
            $strategy = 'trigram';
        }

        if ($candidates->isEmpty()) {
            return $this->familyFallback($query);
        }

        return $this->withinBudget($candidates, $strategy, $query);
    }

    /**
     * Build the search query from the homeowner's message.
     *
     * Two things Postgres will not do for us:
     *
     *  - `plainto_tsquery` joins terms with AND, which is useless on a
     *    conversational sentence: "j'ai de la moisissure autour des fenêtres"
     *    would demand a passage containing all four words. We OR them instead
     *    and let the ranking reward passages matching more of them.
     *  - after unaccenting, one- and two-letter lexemes survive the French stop
     *    list — notably « à » becoming a bare `a`, which then matches every
     *    passage containing "difficile à chauffer". They carry no meaning and
     *    are dropped.
     *
     * Computed once per turn rather than per row.
     */
    private function tsQueryFor(string $text): ?string
    {
        return DB::scalar(
            "select string_agg(lexeme, ' | ')
             from unnest(to_tsvector('french', f_unaccent(?)))
             where length(lexeme) > 2",
            [$text],
        );
    }

    /**
     * @return Collection<int, RetrievedPassage>
     */
    private function fullText(RetrievalQuery $query, string $tsQuery): Collection
    {
        $minRank = (float) config('knowledge.retrieval.min_rank');

        // The family the conversation is already about is the strongest signal we
        // have — stronger than any word in a single message. So it boosts the
        // score rather than only filtering, which keeps a general passage from
        // outranking the on-topic explanation on an incidental word match.
        $boost = (float) config('knowledge.retrieval.family_boost');
        $rank = $query->problemFamily
            ? "ts_rank(search_vector, ?::tsquery) * (case when problem_family = ? then {$boost} else 1 end)"
            : 'ts_rank(search_vector, ?::tsquery)';

        $bindings = $query->problemFamily ? [$tsQuery, $query->problemFamily] : [$tsQuery];

        $candidates = $this->base($query)
            ->selectRaw("knowledge_passages.*, {$rank} as rank", $bindings)
            // Indexed pre-filter…
            ->whereRaw('search_vector @@ ?::tsquery', [$tsQuery])
            // …then the eligibility rule: the homeowner's words must hit the
            // title or the curated keywords (weights A and B). An incidental
            // mention in the body is not a match — that is how common French
            // words end up grounding a reply in an unrelated passage. The body
            // still informs the ranking above.
            ->whereRaw("ts_filter(search_vector, '{a,b}') @@ ?::tsquery", [$tsQuery])
            ->orderByDesc('rank')
            ->orderBy('position')
            // Over-fetch: the token budget may drop some, and the weak tail is
            // filtered in PHP rather than with SQL gymnastics.
            ->limit($query->limit() * 4)
            ->get()
            ->filter(fn (KnowledgePassage $passage) => (float) $passage->rank >= $minRank)
            ->map(fn (KnowledgePassage $passage) => new RetrievedPassage($passage, (float) $passage->rank, 'fts'))
            ->values();

        return $this->dropWeakTail($candidates);
    }

    /**
     * @return Collection<int, RetrievedPassage>
     */
    private function trigram(RetrievalQuery $query, string $text): Collection
    {
        $minSimilarity = (float) config('knowledge.retrieval.min_similarity');

        return $this->base($query)
            ->selectRaw(
                "knowledge_passages.*, similarity(f_unaccent(title || ' ' || coalesce(keywords, '')), f_unaccent(?)) as rank",
                [$text],
            )
            ->whereRaw(
                "similarity(f_unaccent(title || ' ' || coalesce(keywords, '')), f_unaccent(?)) >= ?",
                [$text, $minSimilarity],
            )
            ->orderByDesc('rank')
            ->limit($query->limit() * 2)
            ->get()
            ->map(fn (KnowledgePassage $passage) => new RetrievedPassage($passage, (float) $passage->rank, 'trigram'))
            ->values();
    }

    /**
     * A passage scoring far below the best match is not grounding, it is noise.
     * Three mediocre passages are worse than one good one: they cost tokens and
     * they pull the reply off topic.
     *
     * @param  Collection<int, RetrievedPassage>  $candidates
     * @return Collection<int, RetrievedPassage>
     */
    private function dropWeakTail(Collection $candidates): Collection
    {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $floor = $candidates->first()->score * (float) config('knowledge.retrieval.relative_floor');

        return $candidates->filter(fn (RetrievedPassage $p) => $p->score >= $floor)->values();
    }

    /**
     * Nothing matched the words, but we know what the conversation is about.
     */
    private function familyFallback(RetrievalQuery $query): RetrievalResult
    {
        if (! $query->problemFamily) {
            return RetrievalResult::empty();
        }

        $candidates = $this->base($query)
            ->where('problem_family', $query->problemFamily)
            ->where('content_type', 'education')
            ->orderBy('position')
            ->limit($query->limit())
            ->get()
            ->map(fn (KnowledgePassage $passage) => new RetrievedPassage($passage, 0.0, 'family'))
            ->values();

        if ($candidates->isEmpty()) {
            return RetrievalResult::empty($query->problemFamily);
        }

        return $this->withinBudget($candidates, 'family', $query);
    }

    /**
     * Tag filtering. A passage with no family is general-purpose (process,
     * objections, trust) and stays eligible; a passage scoped to another
     * product is never eligible.
     */
    private function base(RetrievalQuery $query): Builder
    {
        return KnowledgePassage::query()
            ->active()
            ->when($query->problemFamily, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('problem_family', $query->problemFamily)
                    ->orWhereNull('problem_family')
                    ->orWhere('problem_family', 'general'),
            ))
            ->when($query->productId, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->whereNull('product_id')
                    ->orWhere('product_id', $query->productId),
            ))
            ->when($query->contentTypes !== [], fn (Builder $q) => $q->whereIn('content_type', $query->contentTypes));
    }

    /**
     * Enforce both caps. The token budget is what actually protects per-turn
     * cost: a passage someone lengthens in the admin must not silently inflate
     * every prompt.
     *
     * @param  Collection<int, RetrievedPassage>  $candidates
     */
    private function withinBudget(Collection $candidates, string $strategy, RetrievalQuery $query): RetrievalResult
    {
        $budget = $query->tokenBudget();
        $kept = [];
        $tokens = 0;

        foreach ($candidates as $candidate) {
            if (count($kept) >= $query->limit()) {
                break;
            }

            $cost = $candidate->tokens();

            if ($tokens + $cost > $budget) {
                // Keep at least one passage even if it is oversized on its own,
                // otherwise a long passage means no grounding at all.
                if ($kept !== []) {
                    continue;
                }
            }

            $kept[] = $candidate;
            $tokens += $cost;
        }

        return new RetrievalResult($kept, $strategy, $tokens, $query->problemFamily);
    }
}
