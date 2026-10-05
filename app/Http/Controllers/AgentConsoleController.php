<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Creative;
use App\Models\Lead;
use App\Services\Agent\ConversationAgent;
use App\Services\Agent\LeadState;
use App\Services\Ai\ImageInput;
use App\Services\Ai\Providers\PromptProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * A place to actually talk to the agent before WhatsApp is wired up.
 *
 * It runs the production path — same retrieval, same prompt, same rules — and
 * shows the working: what was extracted, what was retrieved, what the lead
 * looks like now. Testing a conversation without seeing that is guesswork.
 */
class AgentConsoleController extends Controller
{
    public function index(Request $request, PromptProviderRegistry $providers): Response
    {
        $conversation = $this->current($request);

        return Inertia::render('Agent/Console', [
            'conversation' => $conversation ? $this->present($conversation) : null,
            'models' => AiModel::active()->orderByDesc('is_default')->orderBy('position')
                ->get(['id', 'name', 'provider', 'model_id', 'is_default'])
                ->map(fn (AiModel $model) => [
                    ...$model->only(['id', 'name', 'provider', 'model_id', 'is_default']),
                    'configured' => collect($providers->status())
                        ->firstWhere('key', $model->provider)['configured'] ?? false,
                ]),
            'providers' => $providers->status(),
            'creatives' => Creative::orderByDesc('id')->limit(25)->get(['id', 'reference'])
                ->map(fn ($c) => ['id' => $c->id, 'reference' => $c->reference]),
            'openers' => [
                'Bonjour, j\'ai beaucoup de condensation chez moi et des taches noires commencent à apparaître.',
                'bonjour jai 63 ans ma maison est a Angers et jai beaucoup de moisissure dans la chambre depuis cet hiver',
                'Ma facture de gaz a doublé cette année, je ne comprends pas.',
                'ma chaudière a 22 ans et elle tombe souvent en panne',
                'je voudrais parler à quelqu\'un',
            ],
        ]);
    }

    public function store(Request $request, ConversationAgent $agent): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1000', 'required_without:images'],
            'ai_model_id' => ['nullable', 'exists:ai_models,id'],
            'creative_id' => ['nullable', 'exists:creatives,id'],
            'images' => ['array', 'max:'.config('agent.images.max_per_message')],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) (config('agent.images.max_bytes') / 1024)],
        ]);

        $conversation = $this->current($request) ?? $this->start($request, $data['creative_id'] ?? null);

        // Keep the uploads so the thread can show them afterwards — the same way
        // a WhatsApp media URL will be kept once the transport is wired.
        $images = collect($request->file('images') ?? [])
            ->map(function ($file) {
                $path = $file->store('agent-uploads', 'public');

                return ImageInput::fromPath(
                    Storage::disk('public')->path($path),
                    $file->getMimeType(),
                    Storage::disk('public')->url($path),
                );
            })
            ->all();

        try {
            $agent->handle(
                $conversation,
                $data['body'] ?? '',
                isset($data['ai_model_id']) ? AiModel::find($data['ai_model_id']) : null,
                $images,
            );
        } catch (Throwable $e) {
            // Never invent a reply: say what went wrong and leave the inbound
            // message in place so the transcript stays honest.
            return back()->with('error', 'Le modèle n\'a pas pu répondre : '.$e->getMessage());
        }

        return back();
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget('agent_console_conversation');

        return back()->with('success', 'Nouvelle conversation.');
    }

    private function current(Request $request): ?Conversation
    {
        $id = $request->session()->get('agent_console_conversation');

        return $id ? Conversation::find($id) : null;
    }

    private function start(Request $request, ?int $creativeId): Conversation
    {
        $conversation = Conversation::create([
            'channel' => 'test',
            'external_id' => 'console-'.Str::random(12),
            'status' => 'active',
            'creative_id' => $creativeId,
            // The console stands in for a Click-to-WhatsApp entry point.
            'referral' => ['source' => 'console', 'started_by' => $request->user()?->email],
        ]);

        $request->session()->put('agent_console_conversation', $conversation->id);

        return $conversation;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Conversation $conversation): array
    {
        $conversation->loadMissing([
            'messages', 'lead.parameters.value', 'lead.parameters.category', 'lead.product', 'creative',
        ]);

        $lead = $conversation->lead;

        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'problem_family' => $conversation->problem_family,
            'creative' => $conversation->creative?->reference,
            'messages' => $conversation->messages->map(fn (ConversationMessage $message) => [
                'id' => $message->id,
                'direction' => $message->direction,
                'body' => $message->body,
                'at' => $message->created_at?->format('H:i:s'),
                'images' => collect($message->raw['images'] ?? [])->pluck('url')->filter()->values(),
                'images_ignored' => $message->raw['ignored_by_provider'] ?? false,
                'meta' => $message->meta,
            ]),
            'lead' => $lead ? $this->presentLead($lead) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLead(Lead $lead): array
    {
        $state = new LeadState($lead);

        return [
            'id' => $lead->id,
            'status' => $lead->qualification_status,
            'status_label' => Lead::STATUSES[$lead->qualification_status] ?? $lead->qualification_status,
            'full_name' => $lead->fullName(),
            'postal_code' => $lead->postal_code,
            'city' => $lead->city,
            'department' => $lead->department,
            'phone' => $lead->phone_e164,
            'product' => $lead->product?->name,
            'details' => $lead->details ?? [],
            'raw_notes' => $lead->raw_notes,
            'parameters' => $lead->parameters->map(fn ($p) => [
                'category' => $p->category?->name,
                'value' => $p->value?->label,
            ])->filter(fn ($p) => $p['value'])->values(),
            'missing' => $state->missingRequired(),
            'next_target' => $state->nextTarget(),
            'summary' => $lead->summary,
        ];
    }
}
