<?php

namespace App\Http\Controllers;

use App\Models\AgentGuardrail;
use App\Models\KnowledgePassage;
use App\Models\Product;
use App\Services\Knowledge\GuardrailComposer;
use App\Services\Knowledge\KnowledgeRetriever;
use App\Services\Knowledge\RetrievalQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's knowledge, and a way to see what it would actually retrieve.
 *
 * The preview matters more than it looks: without it, tuning a passage is
 * guesswork, and nobody can explain why the agent said what it said.
 */
class KnowledgeController extends Controller
{
    public function index(Request $request, KnowledgeRetriever $retriever, GuardrailComposer $guardrails): Response
    {
        $filters = [
            'family' => $request->string('family')->toString() ?: null,
            'content_type' => $request->string('content_type')->toString() ?: null,
            'search' => $request->string('search')->toString() ?: null,
        ];

        $passages = KnowledgePassage::query()
            ->with(['product:id,name,code', 'editor:id,name'])
            ->when($filters['family'], fn ($q) => $q->where('problem_family', $filters['family']))
            ->when($filters['content_type'], fn ($q) => $q->where('content_type', $filters['content_type']))
            ->when($filters['search'], fn ($q, $term) => $q->where(
                fn ($inner) => $inner->where('title', 'ilike', "%{$term}%")
                    ->orWhere('body', 'ilike', "%{$term}%")
                    ->orWhere('keywords', 'ilike', "%{$term}%"),
            ))
            ->orderBy('problem_family')
            ->orderBy('position')
            ->get()
            ->map(fn (KnowledgePassage $passage) => [
                'id' => $passage->id,
                'slug' => $passage->slug,
                'title' => $passage->title,
                'body' => $passage->body,
                'keywords' => $passage->keywords,
                'problem_family' => $passage->problem_family,
                'content_type' => $passage->content_type,
                'product_id' => $passage->product_id,
                'product' => $passage->product?->name,
                'never_claim' => $passage->never_claim,
                'source' => $passage->source,
                'is_active' => $passage->is_active,
                'position' => $passage->position,
                'tokens' => $passage->estimatedTokens(),
                'updated_by' => $passage->editor?->name,
                'updated_at' => $passage->updated_at?->toDateString(),
            ]);

        // "What would the agent retrieve for this message?" — the whole point
        // of the screen.
        $preview = null;
        if ($request->filled('preview')) {
            $result = $retriever->retrieve(new RetrievalQuery(
                text: $request->string('preview')->toString(),
                problemFamily: $request->string('preview_family')->toString() ?: null,
            ));

            $preview = [
                'text' => $request->string('preview')->toString(),
                'family' => $request->string('preview_family')->toString() ?: null,
                ...$result->toLog(),
                'prompt_block' => $result->toPromptBlock(),
                'titles' => array_map(fn ($p) => $p->passage->title, $result->passages),
            ];
        }

        return Inertia::render('Knowledge/Index', [
            'passages' => $passages,
            'guardrails' => AgentGuardrail::orderBy('position')->orderBy('id')->get()
                ->map(fn (AgentGuardrail $rule) => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'kind' => $rule->kind,
                    'body' => $rule->body,
                    'is_active' => $rule->is_active,
                    'position' => $rule->position,
                ]),
            'guardrailBlock' => $guardrails->block(),
            'families' => config('knowledge.families'),
            'contentTypes' => config('knowledge.content_types'),
            'kinds' => AgentGuardrail::KINDS,
            'products' => Product::active()->orderBy('position')->get(['id', 'name']),
            'filters' => $filters,
            'preview' => $preview,
            'budget' => config('knowledge.retrieval'),
        ]);
    }
}
