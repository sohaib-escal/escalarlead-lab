<?php

namespace Tests\Feature;

use App\Models\AgentGuardrail;
use App\Models\KnowledgePassage;
use App\Models\User;
use App\Services\Knowledge\GuardrailComposer;
use App\Services\Knowledge\KnowledgeRetriever;
use App\Services\Knowledge\RetrievalQuery;
use Database\Seeders\KnowledgeSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guardrails are the other half of the knowledge system: always in the prompt,
 * never retrieved. A compliance rule that is only sometimes recalled is worse
 * than no rule, because it looks like it is working.
 */
class AgentGuardrailTest extends TestCase
{
    use RefreshDatabase;

    private GuardrailComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->seed(KnowledgeSeeder::class);

        $this->composer = app(GuardrailComposer::class);
    }

    public function test_the_block_states_the_rules_that_carry_legal_risk(): void
    {
        $block = $this->composer->block();

        foreach (['MaPrimeRénov', 'économie', 'prix', 'santé', 'service de l\'État', 'assistant automatique'] as $needle) {
            $this->assertStringContainsString($needle, $block, "The standing instructions never mention « {$needle} »");
        }
    }

    public function test_the_block_is_ordered_so_the_persona_comes_before_the_prohibitions(): void
    {
        $block = $this->composer->block();

        $this->assertLessThan(
            strpos($block, 'À NE JAMAIS AFFIRMER'),
            strpos($block, 'QUI VOUS ÊTES'),
        );
        $this->assertStringContainsString('COMMENT VOUS PARLEZ', $block);
    }

    public function test_guardrails_are_never_part_of_retrieval(): void
    {
        // A homeowner asking about aid must not depend on a search hit to be
        // told the truth: the rule is in the prompt either way.
        $retrieved = app(KnowledgeRetriever::class)
            ->retrieve(new RetrievalQuery('est ce que j\'ai droit à une prime ?'));

        foreach ($retrieved->passages as $hit) {
            $this->assertInstanceOf(KnowledgePassage::class, $hit->passage);
        }

        $this->assertSame(0, KnowledgePassage::where('content_type', 'guardrail')->count());
        $this->assertGreaterThan(0, AgentGuardrail::where('kind', 'never_claim')->count());
    }

    public function test_deactivating_a_rule_takes_effect_immediately(): void
    {
        $this->assertStringContainsString('MaPrimeRénov', $this->composer->block());

        AgentGuardrail::where('slug', 'never-claim-pas-daides-promises')->update(['is_active' => false]);

        // The block is cached for performance; an admin edit must not wait for it.
        $this->assertStringNotContainsString('MaPrimeRénov', app(GuardrailComposer::class)->block());
    }

    public function test_the_never_claim_list_is_available_on_its_own(): void
    {
        $claims = $this->composer->neverClaims();

        $this->assertGreaterThanOrEqual(5, count($claims));
        $this->assertTrue(
            collect($claims)->contains(fn (string $claim) => str_contains($claim, 'diagnostic')
                || str_contains($claim, 'cause')),
            'No rule forbids diagnosing the house remotely.',
        );
    }

    public function test_an_admin_can_add_and_edit_knowledge_without_a_developer(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post('/admin/knowledge-passages', [
            'title' => 'Ventilation mécanique contrôlée',
            'body' => 'Une VMC renouvelle l\'air en continu.',
            'keywords' => 'vmc bouche extraction',
            'problem_family' => 'humidite',
            'content_type' => 'education',
            'position' => 50,
            'is_active' => true,
        ])->assertRedirect();

        $passage = KnowledgePassage::where('slug', 'ventilation-mecanique-controlee')->firstOrFail();
        $this->assertSame($admin->id, $passage->updated_by);

        // And it is immediately retrievable — no reindex step, no embedding job.
        $slugs = collect(app(KnowledgeRetriever::class)
            ->retrieve(new RetrievalQuery('la ventilation mécanique est-elle utile ?', 'humidite'))->passages)
            ->map(fn ($hit) => $hit->passage->slug);

        $this->assertContains('ventilation-mecanique-controlee', $slugs);
    }

    public function test_the_knowledge_screen_previews_what_the_agent_would_receive(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/knowledge?preview='.urlencode('j\'ai des taches noires au mur').'&preview_family=humidite')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Knowledge/Index')
                ->has('passages')
                ->has('guardrailBlock')
                ->where('preview.strategy', 'fts')
                ->has('preview.prompt_block')
            );
    }
}
