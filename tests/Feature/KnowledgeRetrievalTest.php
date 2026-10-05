<?php

namespace Tests\Feature;

use App\Models\KnowledgePassage;
use App\Models\Product;
use App\Services\Knowledge\KnowledgeRetriever;
use App\Services\Knowledge\RetrievalQuery;
use Database\Seeders\KnowledgeSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The retrieval contract: bring back what the homeowner is actually talking
 * about, nothing else, and never more than the turn can afford.
 */
class KnowledgeRetrievalTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeRetriever $retriever;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxonomySeeder::class);
        $this->seed(KnowledgeSeeder::class);

        $this->retriever = app(KnowledgeRetriever::class);
    }

    /**
     * @return array<int, string>
     */
    private function slugs(string $text, ?string $family = null, ?int $productId = null): array
    {
        $result = $this->retriever->retrieve(new RetrievalQuery(
            text: $text,
            problemFamily: $family,
            productId: $productId,
        ));

        return array_map(fn ($hit) => $hit->passage->slug, $result->passages);
    }

    public function test_it_retrieves_the_subject_the_homeowner_is_talking_about(): void
    {
        $slugs = $this->slugs('J\'ai beaucoup de moisissure autour des fenêtres', 'humidite');

        $this->assertNotEmpty($slugs);

        foreach ($slugs as $slug) {
            $this->assertStringStartsWith('humidite-', $slug, "{$slug} is not about humidity");
        }
    }

    public function test_it_does_not_retrieve_the_rest_of_the_knowledge_base(): void
    {
        $slugs = $this->slugs('de la buée sur les vitres tous les matins', 'humidite');

        $this->assertNotContains('solaire-principe', $slugs);
        $this->assertNotContains('chauffage-chaudiere-ancienne', $slugs);
        $this->assertNotContains('facture-gaz-elevee', $slugs);
    }

    public function test_each_problem_family_lands_on_its_own_knowledge(): void
    {
        $cases = [
            ['ma facture de gaz est très élevée', 'facture', 'facture-gaz-elevee'],
            ['ma chaudière a 20 ans et tombe en panne', 'chauffage', 'chauffage-pannes-repetees'],
            ['il y a une auréole au plafond après la pluie', 'infiltration', 'infiltration-aureole-plafond'],
            ['mes panneaux solaires sont vieux', 'solaire', 'solaire-principe'],
            ['il y a un courant d\'air près de la fenêtre', 'fenetres', 'fenetres-courants-air'],
        ];

        foreach ($cases as [$text, $family, $expected]) {
            $this->assertContains($expected, $this->slugs($text, $family), "« {$text} » missed {$expected}");
        }
    }

    public function test_an_objection_is_answered_from_the_knowledge_base(): void
    {
        $this->assertContains('objection-aides-etat', $this->slugs('est ce que j\'ai droit à maprimerenov ?'));
        $this->assertContains('objection-combien-ca-coute', $this->slugs('combien ça coûte ?'));
        $this->assertContains('faq-locataire', $this->slugs('je suis locataire'));
    }

    public function test_accents_and_typos_do_not_break_retrieval(): void
    {
        // Voice-to-text and phone keyboards drop accents constantly.
        $this->assertContains('humidite-condensation-fenetres', $this->slugs('de la buee sur les vitres', 'humidite'));

        // And a genuinely misspelt word must still land somewhere sensible.
        $slugs = $this->slugs('jai de la mwasisure sur le mur', 'humidite');
        $this->assertNotEmpty($slugs);
        $this->assertStringStartsWith('humidite-', $slugs[0]);
    }

    public function test_a_common_french_word_does_not_match_everything(): void
    {
        // « à » unaccents to a bare "a", which would otherwise match every
        // passage containing "difficile à chauffer".
        $slugs = $this->slugs('est ce que j\'ai droit à maprimerenov ?');

        $this->assertContains('objection-aides-etat', $slugs);
        $this->assertNotContains('isolation-maison-froide', $slugs);
        $this->assertNotContains('chauffage-pompe-a-chaleur-principe', $slugs);
    }

    public function test_it_respects_the_passage_and_token_budget(): void
    {
        $result = $this->retriever->retrieve(new RetrievalQuery(
            text: 'moisissure condensation buée humidité ventilation mur fenêtre odeur',
            problemFamily: 'humidite',
        ));

        $this->assertLessThanOrEqual(config('knowledge.retrieval.max_passages'), count($result->passages));
        $this->assertLessThanOrEqual(config('knowledge.retrieval.max_tokens'), $result->tokens);
    }

    public function test_a_greeting_falls_back_to_the_family_rather_than_guessing(): void
    {
        $known = $this->retriever->retrieve(new RetrievalQuery(text: 'bonjour', problemFamily: 'humidite'));

        $this->assertSame('family', $known->strategy);
        $this->assertNotEmpty($known->passages);

        // With no idea what the conversation is about, inventing grounding is
        // worse than returning nothing.
        $unknown = $this->retriever->retrieve(new RetrievalQuery(text: 'bonjour'));

        $this->assertTrue($unknown->isEmpty());
        $this->assertSame('none', $unknown->strategy);
        $this->assertSame('', $unknown->toPromptBlock());
    }

    public function test_deactivated_passages_are_never_retrieved(): void
    {
        $this->assertContains('objection-combien-ca-coute', $this->slugs('combien ça coûte ?'));

        KnowledgePassage::where('slug', 'objection-combien-ca-coute')->update(['is_active' => false]);

        $this->assertNotContains('objection-combien-ca-coute', $this->slugs('combien ça coûte ?'));
    }

    public function test_a_passage_scoped_to_one_product_stays_out_of_another(): void
    {
        $pac = Product::where('code', 'PAC')->value('id');

        // solaire-principe is scoped to the solar product.
        $slugs = $this->slugs('panneaux solaires toiture', 'solaire', $pac);

        $this->assertNotContains('solaire-principe', $slugs);
    }

    public function test_the_prompt_block_carries_the_never_claim_of_each_passage(): void
    {
        $result = $this->retriever->retrieve(new RetrievalQuery(
            text: 'est ce que j\'ai droit à maprimerenov ?',
        ));

        $block = $result->toPromptBlock();

        $this->assertStringContainsString('à reformuler', $block, 'The agent must be told to paraphrase, not recite.');
        $this->assertStringContainsString('Interdit', $block);
        $this->assertStringContainsString('éligibilité', $block);
    }

    public function test_the_result_explains_itself_for_the_admin(): void
    {
        $log = $this->retriever->retrieve(new RetrievalQuery('buée sur les vitres', 'humidite'))->toLog();

        $this->assertSame('fts', $log['strategy']);
        $this->assertSame('humidite', $log['family']);
        $this->assertArrayHasKey('slug', $log['passages'][0]);
        $this->assertArrayHasKey('score', $log['passages'][0]);
    }
}
