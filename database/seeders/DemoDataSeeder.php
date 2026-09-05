<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Creative;
use App\Models\CreativeHistory;
use App\Models\CreativeMetric;
use App\Models\CreativeNote;
use App\Models\CreativeStatus;
use App\Models\CtaOption;
use App\Models\LandingPage;
use App\Models\LandingPageType;
use App\Models\ParameterCategory;
use App\Models\ParameterValue;
use App\Models\Product;
use App\Models\User;
use App\Services\UtmBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Realistic French renovation demo data so the app is useful on first open.
 */
class DemoDataSeeder extends Seeder
{
    private array $products = [];

    private array $channels = [];

    private array $statuses = [];

    private array $ctas = [];

    private array $values = [];

    private array $categories = [];

    public function run(): void
    {
        $this->cacheReferences();

        $users = $this->users();
        $landingPages = $this->landingPages();
        $campaigns = $this->campaigns($users['buyer']);

        foreach ($this->definitions() as $index => $def) {
            $this->createCreative($def, $index, $users, $landingPages, $campaigns);
        }
    }

    private function cacheReferences(): void
    {
        $this->products = Product::pluck('id', 'code')->all();
        $this->channels = Channel::pluck('id', 'code')->all();
        $this->statuses = CreativeStatus::pluck('id', 'slug')->all();
        $this->ctas = CtaOption::pluck('id', 'slug')->all();
        $this->categories = ParameterCategory::pluck('id', 'slug')->all();

        foreach (ParameterValue::with('category:id,slug')->get() as $value) {
            $this->values[$value->category->slug.'|'.$value->code] = [
                'id' => $value->id,
                'category_id' => $value->parameter_category_id,
            ];
        }
    }

    private function users(): array
    {
        return [
            'admin' => User::updateOrCreate(['email' => 'admin@renovation.fr'], [
                'name' => 'Amine Bensaid',
                'password' => 'password',
                'is_active' => true,
            ]),
            'buyer' => User::updateOrCreate(['email' => 'claire@renovation.fr'], [
                'name' => 'Claire Dubois',
                'password' => 'password',
                'is_active' => true,
            ]),
            'buyer2' => User::updateOrCreate(['email' => 'karim@renovation.fr'], [
                'name' => 'Karim Haddad',
                'password' => 'password',
                'is_active' => true,
            ]),
            'creative' => User::updateOrCreate(['email' => 'lea@renovation.fr'], [
                'name' => 'Léa Moreau',
                'password' => 'password',
                'is_active' => true,
            ]),
        ];
    }

    private function landingPages(): array
    {
        $types = LandingPageType::pluck('id', 'slug')->all();

        $rows = [
            'pac_diag' => ['Diagnostic PAC — France', 'https://renovation-france.fr/diagnostic-pompe-a-chaleur', 'diagnostic', 'PAC', 'v3', 'LP principale PAC, formulaire 4 étapes.'],
            'pac_aides' => ['Aides PAC 2026', 'https://renovation-france.fr/aides-pompe-a-chaleur', 'aides-de-l-etat', 'PAC', 'v2', 'Angle aides de l\'État, simulateur d\'éligibilité.'],
            'solar_calc' => ['Simulateur solaire', 'https://renovation-france.fr/simulateur-solaire', 'solaire', 'SOLAR', 'v2', 'Estimation de production et d\'économies.'],
            'dv_devis' => ['Devis fenêtres', 'https://renovation-france.fr/devis-double-vitrage', 'double-vitrage', 'DV', 'v1', 'Formulaire devis double vitrage.'],
            'general' => ['Rénovation énergétique', 'https://renovation-france.fr/renovation-energetique', 'renovation-generale', null, 'v1', 'LP générique multi-produits.'],
        ];

        $pages = [];

        foreach ($rows as $key => [$name, $url, $type, $productCode, $version, $notes]) {
            $pages[$key] = LandingPage::updateOrCreate(['name' => $name], [
                'url' => $url,
                'landing_page_type_id' => $types[$type] ?? null,
                'product_id' => $productCode ? $this->products[$productCode] : null,
                'version' => $version,
                'notes' => $notes,
                'is_active' => true,
            ]);
        }

        return $pages;
    }

    private function campaigns(User $owner): array
    {
        $rows = [
            'pac_meta' => [
                'PAC France — Meta — Septembre Test 01', 'pac_france_meta_sept', 'PAC',
                'Leads propriétaires qualifiés', ['FB', 'IG'], '2026-09-01', '2026-09-30', 12000, 'active',
                'Test principal PAC sur Meta, focus 55+ propriétaires de maison.',
            ],
            'pac_aides' => [
                'PAC France — Meta — Angle Aides', 'pac_france_aides', 'PAC',
                'Leads éligibles aux aides', ['FB'], '2026-08-15', '2026-10-15', 8000, 'active',
                'Angle MaPrimeRénov, audience 60+.',
            ],
            'solar_meta' => [
                'Solaire France — Meta — Rentrée', 'solar_france_meta', 'SOLAR',
                'Leads solaire maison individuelle', ['FB', 'IG', 'TT'], '2026-09-01', '2026-11-30', 9000, 'active',
                'Test TikTok inclus pour audience 40–59.',
            ],
            'dv_google' => [
                'Double vitrage — Google Search', 'dv_france_google', 'DV',
                'Demandes de devis fenêtres', ['GGL'], '2026-08-01', '2026-12-31', 6000, 'active',
                'Search sur requêtes fenêtres + aides.',
            ],
            'pac_tiktok' => [
                'PAC France — TikTok — Test', 'pac_france_tiktok', 'PAC',
                'Test créa UGC', ['TT'], '2026-09-10', '2026-10-10', 2500, 'draft',
                'Premier test UGC PAC, budget limité.',
            ],
        ];

        $campaigns = [];

        foreach ($rows as $key => [$name, $code, $productCode, $objective, $channelCodes, $start, $end, $budget, $status, $notes]) {
            $campaign = Campaign::updateOrCreate(['name' => $name], [
                'code' => $code,
                'product_id' => $this->products[$productCode],
                'country' => 'France',
                'objective' => $objective,
                'start_date' => $start,
                'end_date' => $end,
                'budget' => $budget,
                'status' => $status,
                'notes' => $notes,
                'created_by' => $owner->id,
            ]);

            $campaign->channels()->sync(collect($channelCodes)->map(fn ($c) => $this->channels[$c])->all());

            $campaigns[$key] = $campaign;
        }

        return $campaigns;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'product' => 'PAC', 'status' => 'winner', 'format' => 'static_image',
                'name' => 'Femme 60-69 — facture chauffage — aides',
                'channels' => ['FB', 'IG'], 'campaign' => 'pac_aides', 'lp' => 'pac_aides', 'cta' => 'verifier-mon-eligibilite',
                'params' => [
                    'gender' => 'W', 'age' => '60-69', 'household' => 'RETIRED', 'property-type' => 'HOUSE',
                    'house-age' => '40-60', 'homeowner' => 'OCCUP', 'income' => 'FIXED', 'aid-awareness' => 'SEEKAID',
                    'heating-system' => 'OIL', 'problem' => 'HEAT', 'specific-problem' => 'HIGHBILL',
                    'trigger' => ['WINTER', 'BILL'], 'motivation' => 'AID', 'objection' => 'PRICEY', 'awareness' => 'AIDSEEK',
                ],
                'hook' => 'Votre chaudière fioul vous coûte de plus en plus cher chaque hiver ?',
                'primary_text' => "Les propriétaires de plus de 60 ans peuvent obtenir jusqu'à 9 000 € d'aides pour remplacer une vieille chaudière par une pompe à chaleur.\n\nVérifiez votre éligibilité en 2 minutes, sans engagement.",
                'headline' => 'Jusqu\'à 9 000 € d\'aides pour votre PAC',
                'ad_description' => 'Éligibilité vérifiée en 2 minutes',
                'concept' => 'Femme de 65 ans regardant sa facture de chauffage à côté d\'une vieille chaudière fioul.',
                'metrics' => [420, 96000, 2100, 84, 42, 70, 46, 21, 12, 4, 19600],
                'notes' => 'Meilleur coût par RDV du compte. Budget augmenté le 12/09.',
            ],
            [
                'product' => 'PAC', 'status' => 'live', 'format' => 'video',
                'name' => 'Femme 60-69 — facture chauffage — aides (vidéo)',
                'channels' => ['FB'], 'campaign' => 'pac_aides', 'lp' => 'pac_aides', 'cta' => 'decouvrir-les-aides',
                'params' => [
                    'gender' => 'W', 'age' => '60-69', 'property-type' => 'HOUSE', 'homeowner' => 'OCCUP',
                    'income' => 'FIXED', 'aid-awareness' => 'KNOWAID', 'heating-system' => 'GAS',
                    'problem' => 'HEAT', 'specific-problem' => 'HIGHBILL', 'trigger' => 'HEARDAID',
                    'motivation' => 'AID', 'objection' => 'ELIGQ',
                ],
                'hook' => 'Les aides de l\'État pour la pompe à chaleur changent en 2026.',
                'primary_text' => "Vous chauffez une maison construite avant 1990 ? Vous pouvez être concernée par les aides à la rénovation.\n\nUn conseiller vérifie votre dossier gratuitement.",
                'headline' => 'Aides PAC 2026 : êtes-vous éligible ?',
                'ad_description' => 'Simulation gratuite',
                'concept' => 'Vidéo 20s, témoignage conseiller expliquant les aides, sous-titres.',
                'metrics' => [310, 74000, 1480, 51, 22, 41, 25, 9, 5, 1, 4900],
            ],
            [
                'product' => 'PAC', 'status' => 'live', 'format' => 'ugc',
                'name' => 'Femme 60-69 — facture chauffage — aides (UGC)',
                'channels' => ['FB', 'IG'], 'campaign' => 'pac_aides', 'lp' => 'pac_diag', 'cta' => 'faire-mon-diagnostic',
                'params' => [
                    'gender' => 'W', 'age' => '60-69', 'property-type' => 'HOUSE', 'homeowner' => 'OWNER',
                    'income' => 'MODEST', 'aid-awareness' => 'SEEKAID', 'heating-system' => 'OIL',
                    'problem' => 'HEAT', 'specific-problem' => 'HIGHBILL', 'trigger' => 'BILL',
                    'motivation' => 'AID', 'objection' => 'TRUST',
                ],
                'hook' => '« J\'ai divisé ma facture de chauffage par deux. »',
                'primary_text' => "Témoignage d'une propriétaire en Normandie : chaudière fioul remplacée par une pompe à chaleur, 1 400 € d'économies la première année.\n\nFaites votre diagnostic gratuit.",
                'headline' => 'Elle a divisé sa facture par deux',
                'ad_description' => 'Diagnostic gratuit',
                'concept' => 'UGC vertical, propriétaire filmée chez elle devant sa nouvelle PAC.',
                'metrics' => [265, 58000, 1190, 39, 15, 30, 17, 6, 3, 1, 4800],
            ],
            [
                'product' => 'PAC', 'status' => 'winner', 'format' => 'static_image',
                'name' => 'Homme 50-59 — chaudière ancienne — économies',
                'channels' => ['FB'], 'campaign' => 'pac_meta', 'lp' => 'pac_diag', 'cta' => 'estimer-mon-projet',
                'params' => [
                    'gender' => 'M', 'age' => '50-59', 'household' => 'COUPLE', 'property-type' => 'HOUSE',
                    'house-age' => '20-40', 'homeowner' => 'OCCUP', 'income' => 'MID',
                    'heating-system' => 'GAS', 'problem' => 'HEAT', 'specific-problem' => 'OLDBOILER',
                    'trigger' => 'PRICEUP', 'motivation' => 'SAVE', 'objection' => 'PRICEY',
                ],
                'hook' => 'Votre chaudière a plus de 15 ans ? Elle vous coûte 40 % de trop.',
                'primary_text' => "Une pompe à chaleur consomme 3 à 4 fois moins qu'une chaudière ancienne.\n\nEstimez vos économies annuelles en 2 minutes.",
                'headline' => 'Estimez vos économies de chauffage',
                'ad_description' => 'Estimation en 2 minutes',
                'concept' => 'Split visuel : vieille chaudière vs unité PAC extérieure, chiffres d\'économies.',
                'metrics' => [560, 118000, 2600, 102, 55, 88, 60, 29, 17, 6, 31200],
            ],
            [
                'product' => 'PAC', 'status' => 'live', 'format' => 'static_image',
                'name' => 'Homme 60-69 — maison froide — confort',
                'channels' => ['FB', 'IG'], 'campaign' => 'pac_meta', 'lp' => 'pac_diag', 'cta' => 'faire-mon-diagnostic',
                'params' => [
                    'gender' => 'M', 'age' => '60-69', 'property-type' => 'HOUSE', 'house-age' => '40-60',
                    'homeowner' => 'OCCUP', 'income' => 'COMF', 'heating-system' => 'ELEC',
                    'problem' => 'HEAT', 'specific-problem' => 'COLDHOUSE', 'trigger' => 'WINTER',
                    'motivation' => 'COMFORT', 'objection' => 'FIT',
                ],
                'hook' => 'Certaines pièces de votre maison ne chauffent jamais vraiment ?',
                'primary_text' => "Une pompe à chaleur air/eau chauffe l'ensemble du logement de manière homogène, même par -7 °C.\n\nDiagnostic gratuit à domicile.",
                'headline' => 'Une maison enfin chaude partout',
                'ad_description' => 'Diagnostic gratuit',
                'concept' => 'Salon chaleureux en hiver, thermostat à 21°, plaid, lumière chaude.',
                'metrics' => [380, 82000, 1560, 47, 19, 38, 21, 8, 4, 1, 5200],
            ],
            [
                'product' => 'PAC', 'status' => 'paused', 'format' => 'carousel',
                'name' => 'Femme 50-59 — panne chaudière — remplacement',
                'channels' => ['FB'], 'campaign' => 'pac_meta', 'lp' => 'pac_diag', 'cta' => 'obtenir-un-devis',
                'params' => [
                    'gender' => 'W', 'age' => '50-59', 'property-type' => 'HOUSE', 'homeowner' => 'OCCUP',
                    'income' => 'MID', 'heating-system' => 'OIL', 'problem' => 'HEAT',
                    'specific-problem' => 'BREAKDOWN', 'trigger' => 'BROKE', 'motivation' => 'REPLACE',
                ],
                'hook' => 'Chaudière en panne ? Ne la réparez pas une fois de plus.',
                'primary_text' => "Le coût d'une réparation de plus vaut souvent mieux investi dans une pompe à chaleur, avec les aides de l'État.\n\nDevis sous 48 h.",
                'headline' => 'Remplacer plutôt que réparer',
                'ad_description' => 'Devis sous 48h',
                'concept' => 'Carrousel 3 slides : panne / coût réparation / installation PAC.',
                'metrics' => [190, 41000, 620, 22, 6, 17, 8, 3, 1, 0, 0],
                'notes' => 'Mise en pause : coût par lead qualifié trop élevé sur audience 50-59.',
            ],
            [
                'product' => 'PAC', 'status' => 'ready', 'format' => 'static_image',
                'name' => 'Femme 70+ — facture chauffage — aides',
                'channels' => ['FB'], 'campaign' => 'pac_aides', 'lp' => 'pac_aides', 'cta' => 'verifier-mon-eligibilite',
                'params' => [
                    'gender' => 'W', 'age' => '70P', 'household' => 'RETIRED', 'property-type' => 'HOUSE',
                    'homeowner' => 'OCCUP', 'income' => 'FIXED', 'aid-awareness' => 'NOAID',
                    'heating-system' => 'OIL', 'problem' => 'HEAT', 'specific-problem' => 'HIGHBILL',
                    'trigger' => 'WINTER', 'motivation' => 'AID',
                ],
                'hook' => 'Retraitée propriétaire ? Vos aides pour la pompe à chaleur sont majorées.',
                'primary_text' => "Les foyers aux revenus modestes bénéficient du montant d'aide le plus élevé.\n\nVérification d'éligibilité gratuite, par téléphone.",
                'headline' => 'Aides majorées pour les revenus modestes',
                'ad_description' => 'Vérification gratuite',
                'concept' => 'Portrait chaleureux d\'une retraitée dans sa cuisine, ton rassurant.',
                'metrics' => null,
            ],
            [
                'product' => 'PAC', 'status' => 'brief', 'format' => 'video',
                'name' => 'Homme 40-49 — rénovation — valorisation',
                'channels' => ['FB', 'IG'], 'campaign' => 'pac_meta', 'lp' => 'general', 'cta' => 'en-savoir-plus',
                'params' => [
                    'gender' => 'M', 'age' => '40-49', 'household' => 'FAMILY', 'property-type' => 'HOUSE',
                    'house-age' => '20-40', 'homeowner' => 'OCCUP', 'income' => 'COMF',
                    'problem' => 'HEAT', 'specific-problem' => 'OLDBOILER', 'trigger' => 'RENO',
                    'motivation' => 'VALUE',
                ],
                'hook' => 'Vous rénovez ? Commencez par le chauffage.',
                'primary_text' => "Une pompe à chaleur améliore le DPE et la valeur de votre maison.\n\nSimulation de gain énergétique gratuite.",
                'headline' => 'Gagnez 2 classes énergétiques',
                'ad_description' => 'Simulation gratuite',
                'concept' => 'Avant/après DPE, maison en rénovation.',
                'metrics' => null,
            ],
            [
                'product' => 'PAC', 'status' => 'idea', 'format' => 'ugc',
                'name' => 'Homme 60-69 — facture chauffage — confort (UGC TikTok)',
                'channels' => ['TT'], 'campaign' => 'pac_tiktok', 'lp' => 'pac_diag', 'cta' => 'faire-mon-diagnostic',
                'params' => [
                    'gender' => 'M', 'age' => '60-69', 'property-type' => 'HOUSE', 'homeowner' => 'OCCUP',
                    'problem' => 'HEAT', 'specific-problem' => 'HIGHBILL', 'trigger' => 'WINTER',
                    'motivation' => 'COMFORT',
                ],
                'hook' => 'Ce que personne ne vous dit sur les vieilles chaudières.',
                'primary_text' => 'Format UGC vertical, ton direct, sous-titres larges.',
                'headline' => 'Chauffage : l\'erreur à éviter',
                'ad_description' => null,
                'concept' => 'Créateur 55+ parlant caméra à la main devant sa chaudière.',
                'metrics' => null,
            ],
            [
                'product' => 'SOLAR', 'status' => 'winner', 'format' => 'static_image',
                'name' => 'Homme 50-59 — facture électricité — indépendance',
                'channels' => ['FB', 'IG'], 'campaign' => 'solar_meta', 'lp' => 'solar_calc', 'cta' => 'estimer-mon-projet',
                'params' => [
                    'gender' => 'M', 'age' => '50-59', 'household' => 'COUPLE', 'property-type' => 'HOUSE',
                    'homeowner' => 'OCCUP', 'income' => 'COMF', 'electricity-situation' => 'HIELEC',
                    'existing-solar' => 'NOSOL', 'consumption' => 'HICONS', 'problem' => 'ELECP',
                    'specific-problem' => 'ELECBILL', 'trigger' => 'PRICEUP', 'motivation' => 'INDEP',
                    'objection' => 'PRICEY',
                ],
                'hook' => 'Votre toit peut produire l\'électricité que vous payez trop cher.',
                'primary_text' => "Une installation solaire couvre en moyenne 60 % de la consommation d'une maison individuelle.\n\nEstimation de production en 2 minutes.",
                'headline' => 'Produisez votre électricité',
                'ad_description' => 'Estimation gratuite',
                'concept' => 'Maison individuelle vue drone avec panneaux, ciel bleu, chiffre d\'économie.',
                'metrics' => [640, 132000, 3100, 118, 61, 96, 66, 30, 18, 7, 46200],
            ],
            [
                'product' => 'SOLAR', 'status' => 'live', 'format' => 'video',
                'name' => 'Femme 50-59 — facture électricité — économies',
                'channels' => ['FB'], 'campaign' => 'solar_meta', 'lp' => 'solar_calc', 'cta' => 'faire-mon-diagnostic',
                'params' => [
                    'gender' => 'W', 'age' => '50-59', 'property-type' => 'HOUSE', 'homeowner' => 'OCCUP',
                    'income' => 'MID', 'electricity-situation' => 'HIELEC', 'existing-solar' => 'NOSOL',
                    'problem' => 'ELECP', 'specific-problem' => 'ELECBILL', 'trigger' => 'BILL',
                    'motivation' => 'SAVE',
                ],
                'hook' => 'Facture d\'électricité en hausse ? Regardez votre toit.',
                'primary_text' => 'Calculez en 2 minutes combien votre toiture pourrait vous faire économiser chaque année.',
                'headline' => 'Combien votre toit peut vous rapporter',
                'ad_description' => 'Simulateur gratuit',
                'concept' => 'Animation simple : facture qui descend, panneaux qui s\'installent.',
                'metrics' => [295, 61000, 1320, 44, 20, 34, 22, 9, 5, 2, 12800],
            ],
            [
                'product' => 'SOLAR', 'status' => 'live', 'format' => 'ugc',
                'name' => 'Homme 40-49 — toiture adaptée — aides (TikTok)',
                'channels' => ['TT'], 'campaign' => 'solar_meta', 'lp' => 'solar_calc', 'cta' => 'verifier-mon-eligibilite',
                'params' => [
                    'gender' => 'M', 'age' => '40-49', 'household' => 'FAMILY', 'property-type' => 'HOUSE',
                    'homeowner' => 'OCCUP', 'area-type' => 'RUR', 'problem' => 'SOL',
                    'specific-problem' => 'ROOF', 'trigger' => 'HEARDAID', 'motivation' => 'AID',
                ],
                'hook' => 'Prime à l\'autoconsommation : ce que vous pouvez récupérer.',
                'primary_text' => "Vérifiez si votre toiture est éligible à la prime à l'autoconsommation.",
                'headline' => 'Votre toiture est-elle éligible ?',
                'ad_description' => 'Vérification 2 min',
                'concept' => 'UGC vertical, créateur montrant sa toiture et son compteur.',
                'metrics' => [180, 96000, 1900, 51, 12, 32, 14, 4, 1, 0, 0],
                'notes' => 'Volume de leads correct mais qualification faible sur TikTok.',
            ],
            [
                'product' => 'SOLAR', 'status' => 'ready', 'format' => 'static_image',
                'name' => 'Homme 60-69 — facture électricité — indépendance',
                'channels' => ['FB'], 'campaign' => 'solar_meta', 'lp' => 'solar_calc', 'cta' => 'estimer-mon-projet',
                'params' => [
                    'gender' => 'M', 'age' => '60-69', 'property-type' => 'HOUSE', 'homeowner' => 'OCCUP',
                    'income' => 'FIXED', 'electricity-situation' => 'HIELEC', 'problem' => 'ELECP',
                    'specific-problem' => 'ELECBILL', 'trigger' => 'BILL', 'motivation' => 'INDEP',
                ],
                'hook' => 'À la retraite, votre facture d\'électricité ne devrait pas augmenter.',
                'primary_text' => 'Sécurisez votre budget énergie pour les 20 prochaines années.',
                'headline' => 'Un budget énergie stable',
                'ad_description' => 'Estimation gratuite',
                'concept' => 'Couple de retraités devant leur maison équipée de panneaux.',
                'metrics' => null,
            ],
            [
                'product' => 'DV', 'status' => 'live', 'format' => 'static_image',
                'name' => 'Femme 60-69 — courants d\'air — confort',
                'channels' => ['FB'], 'campaign' => 'dv_google', 'lp' => 'dv_devis', 'cta' => 'obtenir-un-devis',
                'params' => [
                    'gender' => 'W', 'age' => '60-69', 'property-type' => 'HOUSE', 'house-age' => '60P',
                    'homeowner' => 'OCCUP', 'income' => 'MID', 'problem' => 'WIN',
                    'specific-problem' => 'DRAFTS', 'trigger' => 'WINTER', 'motivation' => 'COMFORT',
                    'objection' => 'PRICEY',
                ],
                'hook' => 'Vous sentez le froid près des fenêtres ?',
                'primary_text' => "Des fenêtres anciennes peuvent représenter jusqu'à 15 % des pertes de chaleur d'une maison.\n\nDevis gratuit sous 48 h.",
                'headline' => 'Fini les courants d\'air',
                'ad_description' => 'Devis gratuit',
                'concept' => 'Main près d\'une fenêtre ancienne, buée, ambiance hiver.',
                'metrics' => [220, 48000, 980, 31, 14, 25, 16, 7, 4, 2, 9600],
            ],
            [
                'product' => 'DV', 'status' => 'live', 'format' => 'static_image',
                'name' => 'Homme 50-59 — fenêtres anciennes — économies (Search)',
                'channels' => ['GGL'], 'campaign' => 'dv_google', 'lp' => 'dv_devis', 'cta' => 'obtenir-un-devis',
                'params' => [
                    'gender' => 'M', 'age' => '50-59', 'property-type' => 'HOUSE', 'homeowner' => 'OCCUP',
                    'income' => 'MID', 'problem' => 'WIN', 'specific-problem' => 'OLDWIN',
                    'trigger' => 'RENO', 'motivation' => 'SAVE',
                ],
                'hook' => 'Remplacer ses fenêtres : le budget réel en 2026.',
                'primary_text' => "Prix, aides disponibles et délais d'installation : recevez une estimation personnalisée.",
                'headline' => 'Devis fenêtres double vitrage',
                'ad_description' => 'Aides incluses',
                'concept' => 'Annonce Search, visuel générique fenêtres neuves.',
                'metrics' => [340, 26000, 1800, 46, 24, 38, 26, 12, 7, 3, 21400],
            ],
            [
                'product' => 'DV', 'status' => 'brief', 'format' => 'carousel',
                'name' => 'Femme 50-59 — condensation — confort',
                'channels' => ['FB', 'IG'], 'campaign' => 'dv_google', 'lp' => 'dv_devis', 'cta' => 'en-savoir-plus',
                'params' => [
                    'gender' => 'W', 'age' => '50-59', 'property-type' => 'APT', 'homeowner' => 'OCCUP',
                    'area-type' => 'URB', 'problem' => 'WIN', 'specific-problem' => 'CONDENS',
                    'trigger' => 'WINTER', 'motivation' => 'COMFORT',
                ],
                'hook' => 'De la buée sur vos vitres tous les matins ?',
                'primary_text' => "La condensation est le premier signe d'un vitrage en fin de vie.",
                'headline' => 'Buée sur les vitres : que faire ?',
                'ad_description' => null,
                'concept' => 'Carrousel pédagogique : causes, conséquences, solution.',
                'metrics' => null,
            ],
            [
                'product' => 'DV', 'status' => 'idea', 'format' => 'video',
                'name' => 'Homme 40-49 — bruit extérieur — confort',
                'channels' => ['FB'], 'campaign' => null, 'lp' => 'dv_devis', 'cta' => 'en-savoir-plus',
                'params' => [
                    'gender' => 'M', 'age' => '40-49', 'property-type' => 'APT', 'area-type' => 'URB',
                    'homeowner' => 'OCCUP', 'problem' => 'WIN', 'specific-problem' => 'NOISE',
                    'trigger' => 'RENO', 'motivation' => 'COMFORT',
                ],
                'hook' => 'Le bruit de la rue vous réveille encore ?',
                'primary_text' => 'Angle isolation phonique, à tester en appartement urbain.',
                'headline' => 'Le silence chez vous',
                'ad_description' => null,
                'concept' => 'Contraste sonore avant/après, ambiance urbaine.',
                'metrics' => null,
            ],
            [
                'product' => 'PAC', 'status' => 'loser', 'format' => 'static_image',
                'name' => 'Homme 40-49 — facture chauffage — aides',
                'channels' => ['FB'], 'campaign' => 'pac_meta', 'lp' => 'pac_aides', 'cta' => 'decouvrir-les-aides',
                'params' => [
                    'gender' => 'M', 'age' => '40-49', 'property-type' => 'APT', 'homeowner' => 'OWNER',
                    'income' => 'MID', 'problem' => 'HEAT', 'specific-problem' => 'HIGHBILL',
                    'trigger' => 'BILL', 'motivation' => 'AID',
                ],
                'hook' => 'Les aides 2026 pour la pompe à chaleur.',
                'primary_text' => "Découvrez les montants d'aide accessibles pour votre logement.",
                'headline' => 'Aides PAC : les montants 2026',
                'ad_description' => 'Simulation gratuite',
                'concept' => 'Visuel institutionnel avec montants d\'aides.',
                'metrics' => [230, 52000, 940, 26, 5, 18, 6, 2, 0, 0, 0],
                'notes' => 'Audience appartement 40-49 : leads non éligibles. À ne pas relancer.',
            ],
            [
                'product' => 'PAC', 'status' => 'archived', 'format' => 'static_image',
                'name' => 'Femme 50-59 — maison froide — confort (v1)',
                'channels' => ['FB'], 'campaign' => null, 'lp' => 'pac_diag', 'cta' => 'faire-mon-diagnostic',
                'params' => [
                    'gender' => 'W', 'age' => '50-59', 'property-type' => 'HOUSE', 'homeowner' => 'OCCUP',
                    'problem' => 'HEAT', 'specific-problem' => 'COLDHOUSE', 'trigger' => 'WINTER',
                    'motivation' => 'COMFORT',
                ],
                'hook' => 'Votre maison met des heures à chauffer ?',
                'primary_text' => 'Ancienne version, remplacée par la v2 en septembre.',
                'headline' => 'Une chaleur homogène',
                'ad_description' => null,
                'concept' => 'Ancienne charte graphique.',
                'metrics' => [140, 30000, 520, 15, 4, 11, 5, 2, 1, 0, 0],
            ],
        ];
    }

    private function createCreative(array $def, int $index, array $users, array $landingPages, array $campaigns): void
    {
        $buyer = $index % 2 === 0 ? $users['buyer'] : $users['buyer2'];

        $reference = $this->buildReference($def, $index);

        $creative = Creative::updateOrCreate(['reference' => $reference], [
            'name' => $def['name'],
            'description' => 'Créa '.$def['name'].' — testée sur '.implode(', ', $def['channels']).'.',
            'product_id' => $this->products[$def['product']],
            'creative_status_id' => $this->statuses[$def['status']],
            'landing_page_id' => $landingPages[$def['lp']]->id ?? null,
            'cta_option_id' => $this->ctas[$def['cta']] ?? null,
            'format' => $def['format'],
            'asset_url' => 'https://assets.renovation-france.fr/creatives/'.Str::lower($reference).'.jpg',
            'asset_filename' => Str::lower($reference).'.jpg',
            'thumbnail_url' => null,
            'hook' => $def['hook'],
            'primary_text' => $def['primary_text'],
            'headline' => $def['headline'],
            'ad_description' => $def['ad_description'],
            'concept' => $def['concept'],
            'performance_override' => null,
            'version' => 1,
            'notes' => $def['notes'] ?? null,
            'created_by' => $buyer->id,
            'updated_by' => $buyer->id,
        ]);

        $creative->channels()->sync(collect($def['channels'])->map(fn ($c) => $this->channels[$c])->all());

        if ($def['campaign']) {
            $creative->campaigns()->sync([$campaigns[$def['campaign']]->id]);
        }

        $creative->parameters()->delete();

        foreach ($def['params'] as $categorySlug => $codes) {
            foreach ((array) $codes as $code) {
                $value = $this->values[$categorySlug.'|'.$code] ?? null;

                if (! $value) {
                    continue;
                }

                $creative->parameters()->create([
                    'parameter_category_id' => $value['category_id'],
                    'parameter_value_id' => $value['id'],
                ]);
            }
        }

        app(UtmBuilder::class)->syncCreative($creative->fresh(['channels', 'campaigns', 'landingPage', 'product']));

        if ($def['metrics']) {
            [$spend, $impressions, $clicks, $leads, $qualified, $contacted, $phoneQualified, $appointments, $confirmed, $sales, $revenue] = $def['metrics'];

            CreativeMetric::updateOrCreate([
                'creative_id' => $creative->id,
                'period_start' => '2026-08-25',
            ], [
                'campaign_id' => $def['campaign'] ? $campaigns[$def['campaign']]->id : null,
                'channel_id' => $this->channels[$def['channels'][0]],
                'period_end' => '2026-09-03',
                'spend' => $spend,
                'impressions' => $impressions,
                'reach' => (int) round($impressions * 0.62),
                'clicks' => $clicks,
                'leads' => $leads,
                'qualified_leads' => $qualified,
                'contacted' => $contacted,
                'phone_qualified' => $phoneQualified,
                'appointments' => $appointments,
                'confirmed' => $confirmed,
                'sales' => $sales,
                'revenue' => $revenue,
                'created_by' => $buyer->id,
            ]);
        }

        if (! empty($def['notes'])) {
            CreativeNote::updateOrCreate(
                ['creative_id' => $creative->id, 'body' => $def['notes']],
                ['user_id' => $buyer->id],
            );
        }

        $this->history($creative, $buyer, $users['creative'], $def['status']);
    }

    private function buildReference(array $def, int $index): string
    {
        $parts = [$def['product']];

        foreach (['gender', 'age', 'specific-problem', 'motivation'] as $slug) {
            if (isset($def['params'][$slug])) {
                $code = (array) $def['params'][$slug];
                $parts[] = $code[0];
            }
        }

        $parts[] = $def['channels'][0];
        $parts[] = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

        return Str::upper(implode('-', $parts));
    }

    private function history(Creative $creative, User $buyer, User $studio, string $status): void
    {
        $creative->history()->delete();

        $timeline = [
            ['created', 'Créa créée', $buyer, 12],
            ['brief', 'Brief envoyé au studio', $buyer, 11],
        ];

        if (! in_array($status, ['idea', 'brief'], true)) {
            $timeline[] = ['asset_uploaded', 'Asset livré par le studio', $studio, 9];
            $timeline[] = ['status_changed', 'Statut passé à Prêt', $buyer, 8];
        }

        if (in_array($status, ['live', 'paused', 'winner', 'loser', 'archived'], true)) {
            $timeline[] = ['status_changed', 'Créa lancée', $buyer, 6];
            $timeline[] = ['metrics_added', 'Performances importées', $buyer, 2];
        }

        if ($status === 'winner') {
            $timeline[] = ['performance', 'Marquée WINNER — budget augmenté', $buyer, 1];
        }

        if ($status === 'paused') {
            $timeline[] = ['status_changed', 'Mise en pause — CPL qualifié trop élevé', $buyer, 1];
        }

        foreach ($timeline as [$event, $description, $user, $daysAgo]) {
            CreativeHistory::create([
                'creative_id' => $creative->id,
                'user_id' => $user->id,
                'event' => $event,
                'description' => $description,
                'created_at' => Carbon::parse('2026-09-04')->subDays($daysAgo),
                'updated_at' => Carbon::parse('2026-09-04')->subDays($daysAgo),
            ]);
        }
    }
}
