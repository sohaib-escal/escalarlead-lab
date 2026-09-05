<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\Channel;
use App\Models\CreativeStatus;
use App\Models\CtaOption;
use App\Models\LandingPageType;
use App\Models\ParameterCategory;
use App\Models\ParameterValue;
use App\Models\Product;
use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Reference data. Everything here is editable from /admin afterwards.
 */
class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $this->products();
        $this->channels();
        $this->statuses();
        $this->ctas();
        $this->landingPageTypes();
        $this->parameters();
        $this->aiModels();
        $this->promptTemplates();
    }

    private function products(): void
    {
        $rows = [
            ['name' => 'Pompe à chaleur', 'code' => 'PAC', 'color' => 'teal', 'description' => 'PAC air/eau et air/air'],
            ['name' => 'Panneaux solaires', 'code' => 'SOLAR', 'color' => 'amber', 'description' => 'Photovoltaïque résidentiel'],
            ['name' => 'Double vitrage', 'code' => 'DV', 'color' => 'sky', 'description' => 'Fenêtres et double vitrage'],
        ];

        foreach ($rows as $i => $row) {
            Product::updateOrCreate(
                ['slug' => Str::slug($row['name'])],
                [...$row, 'position' => $i, 'is_active' => true],
            );
        }
    }

    private function channels(): void
    {
        $rows = [
            ['name' => 'Facebook', 'code' => 'FB', 'default_utm_source' => 'facebook', 'default_utm_medium' => 'paid_social'],
            ['name' => 'Instagram', 'code' => 'IG', 'default_utm_source' => 'instagram', 'default_utm_medium' => 'paid_social'],
            ['name' => 'Google Search', 'code' => 'GGL', 'default_utm_source' => 'google', 'default_utm_medium' => 'cpc'],
            ['name' => 'Google Display', 'code' => 'GDN', 'default_utm_source' => 'google', 'default_utm_medium' => 'display'],
            ['name' => 'TikTok', 'code' => 'TT', 'default_utm_source' => 'tiktok', 'default_utm_medium' => 'paid_social'],
            ['name' => 'Native', 'code' => 'NAT', 'default_utm_source' => 'native', 'default_utm_medium' => 'native'],
            ['name' => 'Autre', 'code' => 'OTH', 'default_utm_source' => 'other', 'default_utm_medium' => 'paid'],
        ];

        foreach ($rows as $i => $row) {
            Channel::updateOrCreate(
                ['slug' => Str::slug($row['name'])],
                [...$row, 'position' => $i, 'is_active' => true],
            );
        }
    }

    private function statuses(): void
    {
        $rows = [
            ['name' => 'Idée', 'slug' => 'idea', 'color' => 'slate'],
            ['name' => 'Brief', 'slug' => 'brief', 'color' => 'violet'],
            ['name' => 'Prompt prêt', 'slug' => 'prompt_ready', 'color' => 'sky'],
            ['name' => 'Génération', 'slug' => 'generating', 'color' => 'amber'],
            ['name' => 'Créé', 'slug' => 'created', 'color' => 'indigo'],
            ['name' => 'Prêt', 'slug' => 'ready', 'color' => 'blue'],
            ['name' => 'En ligne', 'slug' => 'live', 'color' => 'emerald', 'counts_as_live' => true],
            ['name' => 'En pause', 'slug' => 'paused', 'color' => 'amber'],
            ['name' => 'Winner', 'slug' => 'winner', 'color' => 'green', 'counts_as_live' => true],
            ['name' => 'Loser', 'slug' => 'loser', 'color' => 'rose'],
            ['name' => 'Archivé', 'slug' => 'archived', 'color' => 'zinc', 'is_archived_state' => true],
        ];

        foreach ($rows as $i => $row) {
            CreativeStatus::updateOrCreate(
                ['slug' => $row['slug']],
                [...$row, 'position' => $i, 'is_active' => true],
            );
        }
    }

    private function ctas(): void
    {
        $rows = [
            'En savoir plus', 'Vérifier mon éligibilité', 'Faire mon diagnostic',
            'Estimer mon projet', 'Découvrir les aides', 'Obtenir un devis',
        ];

        foreach ($rows as $i => $label) {
            CtaOption::updateOrCreate(
                ['slug' => Str::slug($label)],
                ['label' => $label, 'position' => $i, 'is_active' => true],
            );
        }
    }

    private function landingPageTypes(): void
    {
        $rows = [
            'Rénovation générale', 'Pompe à chaleur', 'Solaire',
            'Double vitrage', 'Aides de l\'État', 'Diagnostic', 'Autre',
        ];

        foreach ($rows as $i => $name) {
            LandingPageType::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'position' => $i, 'is_active' => true],
            );
        }
    }

    /**
     * Some values only make sense for one product — a heat pump creative is never
     * about old windows. Scoping them keeps the tree from proposing nonsense.
     *
     * @var array<string, array<string, string>>
     */
    private const VALUE_PRODUCT_SCOPE = [
        'specific-problem' => [
            'HIGHBILL' => 'PAC',
            'OLDBOILER' => 'PAC',
            'BREAKDOWN' => 'PAC',
            'COLDHOUSE' => 'PAC',
            'ELECBILL' => 'SOLAR',
            'ROOF' => 'SOLAR',
            'DRAFTS' => 'DV',
            'CONDENS' => 'DV',
            'NOISE' => 'DV',
            'OLDWIN' => 'DV',
        ],
        'problem' => [
            'HEAT' => 'PAC',
            'ELECP' => 'SOLAR',
            'SOL' => 'SOLAR',
            'WIN' => 'DV',
        ],
        'heating-system' => [
            'OIL' => 'PAC',
            'GAS' => 'PAC',
            'ELEC' => 'PAC',
            'WOOD' => 'PAC',
            'HASPAC' => 'PAC',
        ],
        'existing-solar' => [
            'NOSOL' => 'SOLAR',
            'HASSOL' => 'SOLAR',
        ],
    ];

    /**
     * The creative tree dimensions. `in_tree` = usable as a tree axis,
     * `in_naming` = contributes to the generated creative reference.
     */
    private function parameters(): void
    {
        $categories = [
            ['gender', 'Genre', 'persona', true, true, [
                ['Homme', 'M'], ['Femme', 'W'],
            ]],
            ['age', 'Âge', 'persona', true, true, [
                ['40–49', '40-49'], ['50–59', '50-59'], ['60–69', '60-69'], ['70+', '70P'],
            ]],
            ['household', 'Foyer', 'persona', false, false, [
                ['Célibataire', 'SINGLE'], ['Couple', 'COUPLE'], ['Couple + enfants', 'FAMILY'],
                ['Nid vide', 'EMPTY'], ['Foyer retraité', 'RETIRED'],
            ]],
            ['employment', 'Statut professionnel', 'persona', false, false, [
                ['Salarié', 'EMPL'], ['Indépendant', 'SELF'], ['Sans emploi', 'UNEMPL'], ['Retraité', 'PENS'],
            ]],
            ['retirement', 'Retraite', 'persona', false, false, [
                ['Retraité', 'RET'], ['Proche de la retraite', 'PRERET'], ['Non retraité', 'NORET'],
            ]],
            ['homeowner', 'Statut de propriété', 'property', false, false, [
                ['Propriétaire', 'OWNER'], ['Propriétaire occupant', 'OCCUP'], ['Locataire', 'TENANT'],
            ]],
            ['property-type', 'Type de bien', 'property', true, false, [
                ['Maison', 'HOUSE'], ['Appartement', 'APT'],
            ]],
            ['house-age', 'Âge du logement', 'property', false, false, [
                ['< 10 ans', 'LT10'], ['10–20 ans', '10-20'], ['20–40 ans', '20-40'],
                ['40–60 ans', '40-60'], ['60+ ans', '60P'],
            ]],
            ['ownership-duration', 'Ancienneté de possession', 'property', false, false, [
                ['< 5 ans', 'OWN5'], ['5–10 ans', 'OWN10'], ['10–20 ans', 'OWN20'], ['20+ ans', 'OWN20P'],
            ]],
            ['area-type', 'Zone', 'property', false, false, [
                ['Urbain', 'URB'], ['Périurbain', 'SUB'], ['Rural', 'RUR'],
            ]],
            ['income', 'Sensibilité au prix', 'financial', false, false, [
                ['Confortable', 'COMF'], ['Revenu moyen', 'MID'], ['Revenu modeste', 'MODEST'],
                ['Retraite / revenu fixe', 'FIXED'], ['Sensible au prix', 'PRICE'],
            ]],
            ['aid-awareness', 'Connaissance des aides', 'financial', false, false, [
                ['Ne connaît pas les aides', 'NOAID'], ['Connaît les aides', 'KNOWAID'],
                ['Recherche des aides', 'SEEKAID'], ['Compare les offres', 'COMPARE'],
            ]],
            ['aid-eligibility', 'Éligibilité aux aides', 'financial', false, false, [
                ['Éligible', 'ELIG'], ['Non éligible', 'NOELIG'], ['Inconnue', 'UNKELIG'],
            ]],
            ['heating-system', 'Système de chauffage', 'energy', false, false, [
                ['Chaudière fioul', 'OIL'], ['Chaudière gaz', 'GAS'], ['Chauffage électrique', 'ELEC'],
                ['Bois / granulés', 'WOOD'], ['PAC existante', 'HASPAC'],
            ]],
            ['electricity-situation', 'Situation électricité', 'energy', false, false, [
                ['Facture élevée', 'HIELEC'], ['Facture moyenne', 'MIDELEC'], ['Facture faible', 'LOWELEC'],
            ]],
            ['existing-solar', 'Solaire existant', 'energy', false, false, [
                ['Aucun', 'NOSOL'], ['Déjà équipé', 'HASSOL'],
            ]],
            ['consumption', 'Consommation', 'energy', false, false, [
                ['Élevée', 'HICONS'], ['Moyenne', 'MIDCONS'], ['Faible', 'LOWCONS'],
            ]],
            ['awareness', 'Niveau de conscience', 'problem', false, false, [
                ['Ne connaît pas la rénovation', 'UNAWARE'], ['Conscient du problème', 'PROBLEM'],
                ['Connaît les solutions', 'SOLUTION'], ['Connaît les aides', 'AIDAWARE'],
                ['Recherche des aides', 'AIDSEEK'], ['Compare les offres', 'CMP'],
            ]],
            ['problem', 'Problème principal', 'problem', true, false, [
                ['Chauffage', 'HEAT'], ['Électricité', 'ELECP'], ['Solaire', 'SOL'], ['Fenêtres', 'WIN'],
            ]],
            ['specific-problem', 'Problème spécifique', 'problem', true, true, [
                ['Facture de chauffage élevée', 'HIGHBILL'], ['Chaudière ancienne', 'OLDBOILER'],
                ['Panne de chaudière', 'BREAKDOWN'], ['Maison froide', 'COLDHOUSE'],
                ['Facture d\'électricité élevée', 'ELECBILL'], ['Toiture adaptée au solaire', 'ROOF'],
                ['Courants d\'air', 'DRAFTS'], ['Condensation', 'CONDENS'],
                ['Bruit extérieur', 'NOISE'], ['Fenêtres anciennes', 'OLDWIN'],
            ]],
            ['symptom', 'Symptôme', 'problem', false, false, [
                ['Radiateurs froids', 'COLDRAD'], ['Chaudière bruyante', 'NOISYBOIL'],
                ['Pièces difficiles à chauffer', 'HARDHEAT'], ['Vitres embuées', 'FOG'],
                ['Facture en hausse', 'BILLUP'],
            ]],
            ['trigger', 'Déclencheur', 'problem', true, false, [
                ['Hiver', 'WINTER'], ['Facture reçue', 'BILL'], ['Panne de chaudière', 'BROKE'],
                ['Projet de rénovation', 'RENO'], ['A entendu parler des aides', 'HEARDAID'],
                ['Hausse des prix de l\'énergie', 'PRICEUP'],
            ]],
            ['motivation', 'Motivation', 'problem', true, true, [
                ['Économiser', 'SAVE'], ['Confort', 'COMFORT'], ['Remplacer un équipement', 'REPLACE'],
                ['Indépendance énergétique', 'INDEP'], ['Valoriser le logement', 'VALUE'],
                ['Aides de l\'État', 'AID'],
            ]],
            ['objection', 'Objection', 'problem', false, false, [
                ['Trop cher', 'PRICEY'], ['Ne fait pas confiance aux installateurs', 'TRUST'],
                ['Ne sait pas si c\'est adapté', 'FIT'], ['Ne connaît pas son éligibilité', 'ELIGQ'],
                ['Veut comparer', 'CMPQ'],
            ]],
        ];

        foreach ($categories as $i => [$slug, $name, $group, $inTree, $inNaming, $values]) {
            $category = ParameterCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'group' => $group,
                    'in_tree' => $inTree,
                    'in_naming' => $inNaming,
                    'is_multi' => in_array($slug, ['trigger', 'objection', 'symptom', 'motivation'], true),
                    'position' => $i,
                    'is_active' => true,
                ],
            );

            $productIds = Product::pluck('id', 'code');

            foreach ($values as $j => [$label, $code]) {
                $productCode = self::VALUE_PRODUCT_SCOPE[$slug][$code] ?? null;

                ParameterValue::updateOrCreate(
                    ['parameter_category_id' => $category->id, 'slug' => Str::slug($label)],
                    [
                        'label' => $label,
                        'code' => $code,
                        'product_id' => $productCode ? $productIds[$productCode] : null,
                        'position' => $j,
                        'is_archived' => false,
                    ],
                );
            }
        }
    }

    private function aiModels(): void
    {
        $rows = [
            ['Claude Opus 5', 'anthropic', 'claude-opus-5', true, 'Modèle par défaut pour rédiger les prompts de génération.'],
            ['Claude Sonnet 5', 'anthropic', 'claude-sonnet-5', false, 'Plus rapide, pour les itérations en volume.'],
            ['Gemini 2.5 Pro', 'gemini', 'gemini-2.5-pro', false, 'Utile quand la génération se fait ensuite avec Veo / Flow.'],
            ['GPT-5', 'openai', 'gpt-5', false, null],
        ];

        foreach ($rows as $i => [$name, $provider, $modelId, $isDefault, $notes]) {
            AiModel::updateOrCreate(
                ['provider' => $provider, 'model_id' => $modelId],
                [
                    'name' => $name,
                    'notes' => $notes,
                    'is_default' => $isDefault,
                    'is_active' => true,
                    'position' => $i,
                ],
            );
        }
    }

    private function promptTemplates(): void
    {
        $system = <<<'TXT'
        You write generation prompts for AI video and image models (Veo / Flow / Imagen).
        You work for a French home-renovation lead-generation team (heat pumps, solar panels, double glazing).

        You are given a structured creative brief: who the viewer is, the problem they have,
        the trigger, their motivation, the angle and the product.

        Turn it into ONE generation prompt, written in English (generation models perform
        better in English) describing a French residential scene.

        Rules:
        - Describe the scene, the person, the setting, the lighting and the mood concretely.
        - The person must look like a real French homeowner of the stated age, at home.
        - Show the problem visually rather than through text overlays.
        - Realistic, warm, trustworthy. Never dramatic, never alarmist.
        - No on-screen text, no logos, no invented government branding, no price claims,
          no "free heat pump" messaging, no exaggerated promises.
        - Do not include any French administrative logos or official-looking documents.
        - End with a short technical line: format, aspect ratio, style, lighting.

        Output only the prompt itself. No preamble, no markdown headings, no explanation.
        TXT;

        $rows = [
            [
                'Vidéo courte — scène résidentielle',
                'video',
                'Prompt vidéo verticale pour Veo / Flow, scène de vie française.',
                $system,
                <<<'TXT'
                Creative brief:

                {{brief}}

                Target format: {{format}} (vertical short-form, 9:16, 8 seconds).
                Channel: {{channel}}.
                Desired viewer response: {{desired_response}}.

                Write the generation prompt.
                TXT,
                true,
            ],
            [
                'Image statique — scène résidentielle',
                'image',
                'Prompt image pour un visuel statique Facebook / Instagram.',
                $system,
                <<<'TXT'
                Creative brief:

                {{brief}}

                Target format: {{format}} (single still image, 4:5).
                Channel: {{channel}}.
                Desired viewer response: {{desired_response}}.

                Write the generation prompt.
                TXT,
                false,
            ],
        ];

        foreach ($rows as $i => [$name, $format, $description, $systemPrompt, $userTemplate, $isDefault]) {
            PromptTemplate::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'target_format' => $format,
                    'description' => $description,
                    'system_prompt' => $systemPrompt,
                    'user_template' => $userTemplate,
                    'is_default' => $isDefault,
                    'is_active' => true,
                    'position' => $i,
                ],
            );
        }
    }
}
