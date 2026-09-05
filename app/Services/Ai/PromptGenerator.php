<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\Creative;
use App\Models\CreativePrompt;
use App\Models\PromptTemplate;
use App\Services\Ai\Providers\PromptProviderRegistry;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class PromptGenerator
{
    public function __construct(
        private readonly PromptProviderRegistry $registry,
        private readonly CreativeOutcome $outcomes,
    ) {}

    /**
     * Turn a creative idea into an editable generation prompt.
     */
    public function generate(
        Creative $creative,
        AiModel $model,
        ?PromptTemplate $template = null,
        string $format = 'video',
    ): CreativePrompt {
        $template ??= PromptTemplate::defaultFor($format)
            ?? throw new RuntimeException('Aucun template de prompt actif.');

        $outcome = $this->outcomes->for($creative);
        $brief = $this->outcomes->toBrief($outcome);

        $provider = $this->registry->get($model->provider);

        $userPrompt = $this->render($template->user_template, [
            'brief' => $brief,
            'format' => $format,
            'product' => $outcome['product'] ?? '',
            'channel' => implode(', ', $outcome['channel'] ?? []),
            'desired_response' => $outcome['desired_response'] ?? '',
        ]);

        $completion = $provider->complete($template->system_prompt, $userPrompt, $model->model_id);

        return $creative->prompts()->create([
            'ai_model_id' => $model->id,
            'prompt_template_id' => $template->id,
            'version' => ($creative->prompts()->max('version') ?? 0) + 1,
            'outcome' => $outcome,
            'body' => $completion->text,
            'status' => CreativePrompt::STATUS_DRAFT,
            'target_format' => $format,
            'created_by' => Auth::id(),
            'meta' => $completion->meta,
        ]);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function render(string $template, array $values): string
    {
        foreach ($values as $key => $value) {
            $template = str_replace('{{'.$key.'}}', (string) $value, $template);
        }

        return $template;
    }
}
