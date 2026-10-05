<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Services\Agent\ConversationAgent;
use App\Services\Ai\ImageInput;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Talk to the agent from the terminal.
 *
 * Same path as the web console and as WhatsApp will be — useful for trying a
 * scripted conversation quickly, or for poking at it interactively.
 */
class AgentChatCommand extends Command
{
    protected $signature = 'agent:chat
        {--model= : model_id to use (defaults to the active default)}
        {--script= : path to a file with one homeowner message per line}
        {--image=* : path to an image to attach to the first message}';

    protected $description = 'Have a conversation with the lead qualification agent';

    public function handle(ConversationAgent $agent): int
    {
        $model = $this->option('model')
            ? AiModel::where('model_id', $this->option('model'))->first()
            : AiModel::default();

        if (! $model) {
            $this->error('No active AI model. Add one in /ai-studio.');

            return self::FAILURE;
        }

        $this->line("<fg=gray>Modèle : {$model->name} ({$model->model_id})</>");

        $conversation = Conversation::create([
            'channel' => 'test',
            'external_id' => 'cli-'.Str::random(10),
            'status' => 'active',
        ]);

        $messages = $this->option('script')
            ? array_filter(array_map('trim', file($this->option('script'))))
            : null;

        $pending = array_map(
            fn (string $path) => ImageInput::fromPath($path),
            (array) $this->option('image'),
        );

        foreach ($messages ?? $this->interactive() as $inbound) {
            $this->newLine();
            $this->line("<fg=green>👤 {$inbound}</>"
                .($pending !== [] ? ' <fg=magenta>[+'.count($pending).' photo]</>' : ''));

            try {
                $turn = $agent->handle($conversation, $inbound, $model, $pending);
                $pending = [];
            } catch (Throwable $e) {
                $this->error('✖ '.$e->getMessage());

                return self::FAILURE;
            }

            $this->line("<fg=cyan>🤖 {$turn->reply}</>");

            $this->line('<fg=gray>   extrait : '.(json_encode($turn->extracted, JSON_UNESCAPED_UNICODE) ?: '—').'</>');
            $this->line('<fg=gray>   savoir  : '.($turn->knowledge->isEmpty()
                ? '—'
                : implode(', ', array_map(fn ($p) => $p->passage->slug, $turn->knowledge->passages))).'</>');
            $this->line('<fg=gray>   statut  : '.$turn->lead->qualification_status
                .' · manque : '.(implode(', ', $turn->lead->missing_required_fields ?? []) ?: 'rien').'</>');
        }

        $this->newLine();
        $this->info('===== RÉSUMÉ POUR LE CONSEILLER =====');
        $this->line($conversation->fresh()->lead?->summary ?? '—');

        return self::SUCCESS;
    }

    /**
     * @return iterable<int, string>
     */
    private function interactive(): iterable
    {
        while (true) {
            $input = $this->ask('👤');

            if (blank($input) || in_array(mb_strtolower($input), ['exit', 'quit'], true)) {
                return;
            }

            yield $input;
        }
    }
}
