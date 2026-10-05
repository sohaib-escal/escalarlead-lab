<?php

namespace Database\Seeders;

use App\Models\AgentGuardrail;
use App\Models\KnowledgePassage;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The agent's business knowledge.
 *
 * Everything here is editable from /knowledge afterwards — this seeder is the
 * starting point, not the source of truth. Passages are short on purpose: the
 * agent paraphrases them into a 25-word reply, it never recites them.
 *
 * Two hard rules run through the content:
 *   - causes are always plural and never asserted ("peut avoir plusieurs causes")
 *   - nothing promises aid, savings, eligibility, prices or health outcomes
 */
class KnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $this->guardrails();
        $this->passages();
    }

    private function guardrails(): void
    {
        $rows = [
            ['persona', 'Rôle', 'Vous êtes l\'assistant d\'une société française de rénovation énergétique. Vous accueillez des propriétaires qui répondent à une publicité au sujet d\'un problème dans leur logement.'],
            ['persona', 'Objectif', 'Votre rôle est de comprendre leur situation et de préparer le passage d\'un conseiller. Vous ne vendez rien et vous ne concluez rien.'],

            ['tone', 'Une question à la fois', 'Posez une seule question par message. Jamais deux.'],
            ['tone', 'Messages courts', 'Restez sous 25 mots. Jamais de paragraphe, jamais de liste à puces.'],
            ['tone', 'Français simple', 'Vocabulaire courant. Pas de jargon technique (VMC double flux, COP, ITE, résistance thermique), pas d\'anglais.'],
            ['tone', 'Accuser réception', 'Commencez par reconnaître ce que la personne dit — « Je comprends. », « D\'accord, merci. » — puis posez votre question.'],
            ['tone', 'Sobriété', 'Au plus un emoji, et le plus souvent aucun. Pas de ton commercial, pas de superlatifs.'],
            ['tone', 'Patience', 'Votre interlocuteur a souvent plus de 60 ans et écrit parfois à la voix. Acceptez les fautes, les réponses courtes et les phrases incomplètes sans jamais le faire remarquer.'],

            ['rule', 'Ne jamais répéter une question', 'Ne redemandez jamais une information déjà donnée ou déductible de ce qui a été dit.'],
            ['rule', 'Numéro de téléphone', 'Le numéro WhatsApp est déjà connu. Ne le demandez pas, sauf si la personne souhaite être rappelée sur une autre ligne.'],
            ['rule', 'Informations minimales', 'Vous avez besoin du nom, du code postal et du problème. Le reste est utile mais facultatif.'],
            ['rule', 'Sortie polie', 'Si la personne ne veut pas répondre ou veut arrêter, acceptez immédiatement et remerciez-la.'],
            ['rule', 'Hors sujet', 'Si la demande sort de la rénovation énergétique du logement, dites-le simplement et proposez de transmettre à un conseiller.'],

            ['never_claim', 'Pas de diagnostic à distance', 'Ne jamais affirmer la cause d\'un problème sans visite. Dire que plusieurs causes sont possibles.'],
            ['never_claim', 'Pas d\'aides promises', 'Ne jamais promettre une aide, une prime, MaPrimeRénov\', un montant ou une éligibilité.'],
            ['never_claim', 'Pas d\'économies chiffrées', 'Ne jamais garantir une économie, un pourcentage de réduction ou un gain sur la facture.'],
            ['never_claim', 'Pas de prix', 'Ne jamais annoncer un prix, un devis ou un coût de travaux.'],
            ['never_claim', 'Pas de santé', 'Ne jamais faire d\'affirmation sur les effets de l\'humidité ou des moisissures sur la santé.'],
            ['never_claim', 'Pas de service public', 'Ne jamais se présenter comme un service de l\'État, une agence publique ou un organisme officiel, ni laisser croire que c\'est le cas.'],
            ['never_claim', 'Pas d\'invention', 'Ne jamais inventer un rendez-vous, un technicien, un nom, un délai précis ou une disponibilité.'],

            ['disclosure', 'Assistant automatique', 'Si on vous demande si vous êtes une vraie personne, répondez franchement et simplement que vous êtes un assistant automatique et qu\'un conseiller prendra le relais. Ne le niez jamais.'],
            ['disclosure', 'Usage des données', 'Si on vous demande à quoi servent les informations, expliquez en une phrase qu\'elles permettent à un conseiller de rappeler la personne au sujet de son problème.'],
        ];

        foreach ($rows as $i => [$kind, $name, $body]) {
            AgentGuardrail::updateOrCreate(
                ['slug' => Str::slug($kind.'-'.$name)],
                ['name' => $name, 'kind' => $kind, 'body' => $body, 'is_active' => true, 'position' => $i],
            );
        }
    }

    private function passages(): void
    {
        $products = Product::pluck('id', 'code');

        foreach ($this->content() as $i => $row) {
            KnowledgePassage::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'body' => trim($row['body']),
                    'keywords' => $row['keywords'] ?? null,
                    'problem_family' => $row['family'],
                    'content_type' => $row['type'] ?? 'education',
                    'product_id' => isset($row['product']) ? ($products[$row['product']] ?? null) : null,
                    'never_claim' => $row['never'] ?? null,
                    'audience' => 'homeowner',
                    'source' => 'seed',
                    'is_active' => true,
                    'position' => $i,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function content(): array
    {
        return [
            // ---------------------------------------------------------------
            // Humidité · moisissures · condensation
            // ---------------------------------------------------------------
            [
                'slug' => 'humidite-taches-noires-murs',
                'family' => 'humidite',
                'title' => 'Taches noires sur les murs',
                'keywords' => 'moisissure moisissures taches noires champignon noir mur angle plafond derrière les meubles salpêtre',
                'body' => "Des taches noires dans les angles, derrière un meuble ou au bas d'un mur signalent presque toujours un excès d'humidité. Les causes possibles sont multiples : ventilation insuffisante, humidité qui remonte du sol, infiltration, ou un mur froid sur lequel l'humidité de l'air se dépose. Seule une visite permet de savoir laquelle.",
                'never' => 'ne pas désigner une cause unique, ne pas parler de santé',
            ],
            [
                'slug' => 'humidite-condensation-fenetres',
                'family' => 'humidite',
                'title' => 'Condensation sur les fenêtres',
                'keywords' => 'buée condensation vitre vitres fenêtre gouttes eau le matin hiver embuées',
                'body' => "De la buée ou des gouttes sur les vitres, surtout le matin en hiver, vient d'un air chargé en humidité qui rencontre une surface froide. Cela peut venir d'une ventilation insuffisante, d'un vitrage ancien, ou simplement de la vie quotidienne : cuisine, douches, linge qui sèche à l'intérieur.",
                'never' => 'ne pas affirmer que le vitrage est seul en cause',
            ],
            [
                'slug' => 'humidite-odeur-renferme',
                'family' => 'humidite',
                'title' => 'Odeur de renfermé ou d\'humidité',
                'keywords' => 'odeur sent mauvais renfermé moisi humide cave sous-sol placard',
                'body' => "Une odeur de renfermé persistante accompagne souvent une humidité qui stagne, parfois invisible : derrière un meuble, dans un placard, sous un plancher ou dans une cave. L'odeur apparaît en général avant les taches.",
            ],
            [
                'slug' => 'humidite-papier-peint-decolle',
                'family' => 'humidite',
                'title' => 'Papier peint ou peinture qui se décolle',
                'keywords' => 'papier peint décolle cloque peinture qui s\'écaille enduit gonfle bas du mur plinthe',
                'body' => "Un revêtement qui cloque ou se décolle, surtout dans le bas d'un mur, indique que le mur reste humide. Repeindre par-dessus ne règle pas la cause et le problème revient en général à la saison suivante.",
                'never' => 'ne pas promettre une disparition définitive',
            ],
            [
                'slug' => 'humidite-ventilation-role',
                'family' => 'humidite',
                'title' => 'Le rôle de la ventilation',
                'keywords' => 'vmc ventilation aération grille aérer fenêtre ouverte air renouvelé bouche extraction',
                'body' => "Un logement produit chaque jour beaucoup de vapeur d'eau. Sans renouvellement d'air suffisant — VMC, grilles d'aération, ou ouverture régulière — cette humidité reste à l'intérieur et se dépose sur les surfaces les plus froides. C'est une piste fréquente, parmi d'autres.",
            ],
            [
                'slug' => 'humidite-remontees-capillaires',
                'family' => 'humidite',
                'title' => 'Humidité qui remonte du sol',
                'keywords' => 'remontée capillaire bas du mur humide sol pied de mur salpêtre maison ancienne rez-de-chaussée',
                'body' => "Dans les constructions anciennes, l'humidité du sol peut remonter dans les murs. On la reconnaît souvent à une bande humide en bas du mur, parfois avec des dépôts blancs. Le diagnostic demande une visite : d'autres causes donnent le même aspect.",
            ],
            [
                'slug' => 'humidite-questions-utiles',
                'family' => 'humidite',
                'type' => 'process',
                'title' => 'Ce qu\'il est utile de savoir sur un problème d\'humidité',
                'keywords' => 'où depuis quand quelle pièce hiver ventilation',
                'body' => "Pour préparer la visite d'un conseiller, les éléments utiles sont : dans quelle pièce, depuis quand, si c'est pire en hiver, s'il y a de la buée sur les vitres, s'il existe une VMC ou des grilles d'aération, et s'il s'agit d'une maison ou d'un appartement.",
            ],

            // ---------------------------------------------------------------
            // Infiltration
            // ---------------------------------------------------------------
            [
                'slug' => 'infiltration-aureole-plafond',
                'family' => 'infiltration',
                'title' => 'Auréole au plafond',
                'keywords' => 'auréole tache plafond fuite eau pluie toit toiture goutte tache marron',
                'body' => "Une auréole au plafond qui s'agrandit après la pluie fait penser à une entrée d'eau par la toiture ou par une jonction. L'origine se situe souvent à plusieurs mètres de la tache visible, ce qui rend le repérage à distance impossible.",
                'never' => 'ne pas estimer un coût de réparation',
            ],
            [
                'slug' => 'infiltration-mur-apres-pluie',
                'family' => 'infiltration',
                'title' => 'Mur humide après la pluie',
                'keywords' => 'mur mouillé après la pluie façade fissure pluie battante humide par endroits',
                'body' => "Un mur qui devient humide seulement après la pluie oriente vers une entrée d'eau par la façade, une fissure, un appui de fenêtre ou une gouttière. À l'inverse, une humidité permanente oriente vers d'autres causes.",
            ],
            [
                'slug' => 'infiltration-fenetre',
                'family' => 'infiltration',
                'title' => 'Eau autour d\'une fenêtre',
                'keywords' => 'eau entre par la fenêtre appui rebord joint mastic infiltration fenêtre',
                'body' => "De l'eau qui apparaît sous ou autour d'un encadrement de fenêtre peut venir du joint, de l'appui, ou d'une infiltration plus haut dans le mur. Il est utile de noter si cela arrive uniquement par vent et pluie.",
            ],

            // ---------------------------------------------------------------
            // Isolation · maison froide
            // ---------------------------------------------------------------
            [
                'slug' => 'isolation-maison-froide',
                'family' => 'isolation',
                'title' => 'Maison difficile à chauffer',
                'keywords' => 'maison froide difficile à chauffer jamais chaud chauffage tourne tout le temps pièce froide',
                'body' => "Quand le chauffage tourne en permanence sans que le logement soit confortable, la chaleur s'échappe plus vite qu'elle n'est produite. Les pertes se font le plus souvent par la toiture, les murs, les fenêtres ou le plancher bas, dans des proportions qui varient d'un logement à l'autre.",
                'never' => 'ne pas annoncer un gain chiffré',
            ],
            [
                'slug' => 'isolation-murs-froids',
                'family' => 'isolation',
                'title' => 'Sensation de mur froid',
                'keywords' => 'mur froid paroi froide sensation de froid courant froid dos au mur pièce inconfortable',
                'body' => "Une paroi nettement plus froide que l'air de la pièce crée une sensation d'inconfort même quand le thermostat affiche une bonne température. C'est un indice d'isolation faible à cet endroit, et c'est aussi là que l'humidité de l'air a tendance à se déposer.",
            ],
            [
                'slug' => 'isolation-combles',
                'family' => 'isolation',
                'title' => 'Combles et toiture',
                'keywords' => 'combles grenier toiture isolation laine sous les toits étage chaud en été',
                'body' => "La toiture est en général le premier poste de perte de chaleur d'une maison, car l'air chaud monte. Un étage très chaud en été et difficile à chauffer en hiver va souvent dans ce sens.",
            ],
            [
                'slug' => 'isolation-age-logement',
                'family' => 'isolation',
                'title' => 'Âge du logement et isolation',
                'keywords' => 'maison ancienne construite en 1970 1980 avant 1975 sans isolation vieille maison',
                'body' => "Les logements construits avant les premières réglementations thermiques des années 1970 ont souvent peu ou pas d'isolation d'origine. Savoir l'année approximative de construction et les travaux déjà réalisés aide beaucoup le conseiller.",
            ],

            // ---------------------------------------------------------------
            // Chauffage
            // ---------------------------------------------------------------
            [
                'slug' => 'chauffage-chaudiere-ancienne',
                'family' => 'chauffage',
                'product' => 'PAC',
                'title' => 'Chaudière ancienne',
                'keywords' => 'vieille chaudière chaudière ancienne fioul gaz 15 ans 20 ans remplacer chaudière',
                'body' => "Une chaudière de plus de quinze ans consomme en général nettement plus qu'un équipement récent pour le même confort. Son âge, son énergie et son état d'entretien sont les premières choses qu'un conseiller regarde.",
                'never' => 'ne pas promettre une aide ni un montant',
            ],
            [
                'slug' => 'chauffage-pannes-repetees',
                'family' => 'chauffage',
                'title' => 'Pannes à répétition',
                'keywords' => 'panne chaudière en panne réparation tombe en panne dépannage chauffagiste bruit chaudière',
                'body' => "Des pannes qui reviennent, surtout en hiver, posent la question de savoir si les réparations successives coûtent plus cher que le remplacement. C'est une comparaison qu'un conseiller peut faire avec vous, chiffres en main.",
            ],
            [
                'slug' => 'chauffage-radiateurs-electriques',
                'family' => 'chauffage',
                'title' => 'Chauffage électrique ancien',
                'keywords' => 'convecteur grille-pain radiateur électrique ancien chauffage électrique facture élevée',
                'body' => "Les anciens convecteurs électriques chauffent fort mais répartissent mal la chaleur, ce qui donne des pièces inégales et une facture élevée. L'âge des appareils et le niveau d'isolation du logement comptent autant l'un que l'autre.",
            ],
            [
                'slug' => 'chauffage-pompe-a-chaleur-principe',
                'family' => 'chauffage',
                'product' => 'PAC',
                'title' => 'Principe d\'une pompe à chaleur',
                'keywords' => 'pompe à chaleur pac air eau fonctionnement comment ça marche',
                'body' => "Une pompe à chaleur récupère la chaleur présente dans l'air extérieur pour chauffer le logement, au lieu de produire la chaleur en brûlant un combustible. Son intérêt dépend du logement, de son isolation et du système de distribution en place : cela se vérifie sur site.",
                'never' => 'ne pas garantir une économie ni un rendement',
            ],
            [
                'slug' => 'chauffage-pieces-inegales',
                'family' => 'chauffage',
                'title' => 'Pièces chauffées inégalement',
                'keywords' => 'une pièce froide chambre froide étage froid radiateur tiède mal réparti',
                'body' => "Quand certaines pièces restent froides alors que d'autres sont confortables, cela peut venir du réglage, d'un radiateur à purger, de la circulation d'eau ou d'une isolation faible à cet endroit précis. Plusieurs explications coexistent souvent.",
            ],

            // ---------------------------------------------------------------
            // Factures
            // ---------------------------------------------------------------
            [
                'slug' => 'facture-gaz-elevee',
                'family' => 'facture',
                'title' => 'Facture de gaz élevée',
                'keywords' => 'facture gaz chère augmente prix du gaz mensualité trop cher chauffage',
                'body' => "Une facture de gaz qui augmente sans changement d'habitudes vient souvent de deux choses à la fois : le prix de l'énergie, et un logement qui laisse partir la chaleur. Connaître le montant approximatif et le type de chauffage permet de situer la situation.",
                'never' => 'ne pas garantir une baisse de facture',
            ],
            [
                'slug' => 'facture-electricite-elevee',
                'family' => 'facture',
                'product' => 'SOLAR',
                'title' => 'Facture d\'électricité élevée',
                'keywords' => 'facture électricité chère edf kwh abonnement consommation électrique augmente',
                'body' => "Une facture d'électricité élevée s'explique surtout par le chauffage et l'eau chaude quand ils sont électriques, puis par l'isolation du logement. Le montant annuel approximatif est l'information la plus utile pour un conseiller.",
            ],
            [
                'slug' => 'facture-fioul',
                'family' => 'facture',
                'title' => 'Consommation de fioul',
                'keywords' => 'fioul cuve litres remplissage citerne mazout prix du fioul',
                'body' => "Le fioul se mesure facilement : le nombre de litres consommés dans l'année donne une image directe du besoin de chauffage du logement. C'est un repère précieux, souvent plus parlant qu'un montant mensuel.",
            ],
            [
                'slug' => 'facture-ce-qui-aide',
                'family' => 'facture',
                'type' => 'process',
                'title' => 'Chiffres utiles sur les factures',
                'keywords' => 'montant par mois par an combien je paye estimation',
                'body' => "Un ordre de grandeur suffit : un montant par mois ou par an, et l'énergie concernée. Personne n'attend un chiffre exact, et il n'est pas nécessaire de chercher les factures tout de suite.",
            ],

            // ---------------------------------------------------------------
            // Fenêtres
            // ---------------------------------------------------------------
            [
                'slug' => 'fenetres-courants-air',
                'family' => 'fenetres',
                'product' => 'DV',
                'title' => 'Courants d\'air aux fenêtres',
                'keywords' => 'courant d\'air ça souffle filet d\'air fenêtre qui ferme mal joint usé froid près de la fenêtre',
                'body' => "Un filet d'air froid près d'une fenêtre vient souvent d'un joint usé, d'une menuiserie déformée ou d'un dormant qui ne ferme plus correctement. La sensation est d'autant plus forte que la pièce est chauffée.",
            ],
            [
                'slug' => 'fenetres-simple-vitrage',
                'family' => 'fenetres',
                'product' => 'DV',
                'title' => 'Simple vitrage',
                'keywords' => 'simple vitrage ancienne fenêtre bois vitrage fin une seule vitre',
                'body' => "Une fenêtre en simple vitrage reste très froide en hiver : la chaleur s'échappe par la vitre et l'humidité de l'air s'y dépose. C'est souvent la fenêtre la plus inconfortable d'une pièce.",
            ],
            [
                'slug' => 'fenetres-buee-entre-vitres',
                'family' => 'fenetres',
                'product' => 'DV',
                'title' => 'Buée entre les deux vitres',
                'keywords' => 'buée entre les vitres double vitrage embué vitrage opaque condensation intérieur vitrage',
                'body' => "De la buée emprisonnée entre les deux vitres d'un double vitrage signale que l'étanchéité du vitrage n'est plus assurée. Le vitrage ne joue alors plus pleinement son rôle. C'est l'un des rares constats que l'on peut faire sans visite.",
            ],
            [
                'slug' => 'fenetres-bruit',
                'family' => 'fenetres',
                'product' => 'DV',
                'title' => 'Bruit extérieur',
                'keywords' => 'bruit rue circulation voisins j\'entends tout isolation phonique acoustique',
                'body' => "Quand le bruit de la rue gêne à l'intérieur, les fenêtres sont souvent le point faible. Le niveau de gêne dépend du vitrage, de la pose et de la façade : cela s'évalue sur place.",
                'never' => 'ne pas promettre une suppression du bruit',
            ],

            // ---------------------------------------------------------------
            // Solaire
            // ---------------------------------------------------------------
            [
                'slug' => 'solaire-principe',
                'family' => 'solaire',
                'product' => 'SOLAR',
                'title' => 'Panneaux solaires, principe',
                'keywords' => 'panneau solaire photovoltaïque produire son électricité autoconsommation toiture',
                'body' => "Des panneaux photovoltaïques produisent de l'électricité que l'on consomme d'abord chez soi. Ce que cela représente réellement dépend de la toiture, de son orientation et de la consommation du foyer — cela s'étudie au cas par cas.",
                'never' => 'ne pas promettre une production, un revenu ni une prime',
            ],
            [
                'slug' => 'solaire-toiture-adaptee',
                'family' => 'solaire',
                'product' => 'SOLAR',
                'title' => 'Toiture adaptée ou non',
                'keywords' => 'toit orientation sud ombre arbre surface toiture pente tuiles',
                'body' => "L'orientation, l'inclinaison, la surface disponible et les ombres portées déterminent si une toiture se prête à une installation. Beaucoup de maisons conviennent, mais cela se vérifie, jamais à distance.",
            ],
            [
                'slug' => 'solaire-deja-equipe',
                'family' => 'solaire',
                'product' => 'SOLAR',
                'title' => 'Installation déjà existante',
                'keywords' => 'déjà des panneaux installation existante ajouter des panneaux batterie',
                'body' => 'Quand une installation existe déjà, les questions utiles sont son âge, sa puissance et si la production couvre les besoins actuels. Les besoins évoluent, notamment avec une voiture électrique ou une pompe à chaleur.',
            ],

            // ---------------------------------------------------------------
            // Déroulement · confiance · objections (famille générale)
            // ---------------------------------------------------------------
            [
                'slug' => 'process-deroulement',
                'family' => 'general',
                'type' => 'process',
                'title' => 'Comment cela se passe',
                'keywords' => 'comment ça se passe et après la suite prochaine étape rendez-vous',
                'body' => "Quelques questions pour comprendre la situation, puis un conseiller rappelle pour en parler et convenir d'une visite si c'est pertinent. Rien n'est engagé à ce stade.",
                'never' => 'ne pas annoncer un horaire ni un nom précis',
            ],
            [
                'slug' => 'process-diagnostic-sur-place',
                'family' => 'general',
                'type' => 'process',
                'title' => 'Pourquoi une visite est nécessaire',
                'keywords' => 'diagnostic visite expert venir voir sur place constat',
                'body' => "À distance, on ne peut qu'évoquer des pistes. Un professionnel qui se déplace voit le logement, mesure et détermine la cause réelle — c'est la seule façon d'être sûr.",
            ],
            [
                'slug' => 'process-sans-engagement',
                'family' => 'general',
                'type' => 'process',
                'title' => 'Sans engagement',
                'keywords' => 'engagement obligé signer gratuit ça m\'engage à quoi',
                'body' => "Échanger avec un conseiller n'engage à rien. Vous restez libre de ne pas donner suite, à n'importe quel moment.",
            ],
            [
                'slug' => 'objection-vous-voulez-me-vendre',
                'family' => 'general',
                'type' => 'objection',
                'title' => 'Crainte d\'un démarchage commercial',
                'keywords' => 'vendre commercial arnaque démarchage vous voulez me vendre c\'est pour me vendre quelque chose',
                'body' => "Réponse honnête : nous sommes bien une entreprise de rénovation, et l'objectif est de comprendre si nous pouvons aider. Pas de vente par message, et la personne décide ensuite si elle veut parler à un conseiller.",
            ],
            [
                'slug' => 'objection-combien-ca-coute',
                'family' => 'general',
                'type' => 'objection',
                'title' => 'Question de prix',
                'keywords' => 'prix tarif combien coût devis budget cher combien ça coûte ça vaut combien',
                'body' => 'Aucun prix ne peut être annoncé sans avoir vu le logement : cela dépend trop de la situation. Un conseiller peut donner un ordre de grandeur après la visite.',
                'never' => 'ne jamais citer un prix, même approximatif',
            ],
            [
                'slug' => 'objection-aides-etat',
                'family' => 'general',
                'type' => 'objection',
                'title' => 'Aides de l\'État et éligibilité',
                'keywords' => 'aide aides maprimerenov prime état subvention éligible éligibilité gratuit j\'ai droit aux aides',
                'body' => "Des dispositifs d'aide existent, mais l'éligibilité dépend de la situation de chacun et évolue. Rien ne peut être confirmé par message : un conseiller pourra faire le point précisément.",
                'never' => 'ne jamais confirmer une éligibilité, un montant, ni laisser croire à un dispositif public',
            ],
            [
                'slug' => 'objection-qui-etes-vous',
                'family' => 'general',
                'type' => 'objection',
                'title' => 'Identité de la société',
                'keywords' => 'qui êtes vous quelle société entreprise nom vous venez de l\'état edf vous êtes qui',
                'body' => "Donner simplement le nom de la société et préciser qu'il s'agit d'une entreprise privée de rénovation énergétique, sans lien avec un organisme public.",
                'never' => 'ne jamais se présenter comme un service public ou un fournisseur d\'énergie',
            ],
            [
                'slug' => 'objection-donnees-personnelles',
                'family' => 'general',
                'type' => 'objection',
                'title' => 'Réticence à communiquer ses coordonnées',
                'keywords' => 'données personnelles rgpd pourquoi mon nom code postal je ne donne pas mes infos je ne veux pas donner',
                'body' => "Accepter sans insister. Expliquer en une phrase que le nom et le code postal servent uniquement à ce qu'un conseiller de la bonne zone puisse rappeler, et laisser la personne libre.",
            ],
            [
                'slug' => 'objection-deja-demarche',
                'family' => 'general',
                'type' => 'objection',
                'title' => 'Lassitude face au démarchage téléphonique',
                'keywords' => 'déjà appelé démarchage téléphonique harcelé plusieurs entreprises marre j\'ai déjà eu beaucoup d\'appels',
                'body' => "Reconnaître la lassitude, ne pas insister, et proposer simplement de transmettre la demande si la personne le souhaite. Un refus clair vaut mieux qu'une relance.",
            ],
            [
                'slug' => 'objection-parler-a-quelqu-un',
                'family' => 'general',
                'type' => 'objection',
                'title' => 'Demande de contact humain',
                'keywords' => 'parler à quelqu\'un rappeler téléphone humain conseiller appelez moi je voudrais parler à quelqu\'un',
                'body' => "Accepter immédiatement : confirmer qu'un conseiller rappellera, et demander seulement ce qui manque encore pour le permettre — en général le nom et le code postal.",
                'never' => 'ne pas annoncer d\'heure précise ni de nom de conseiller',
            ],
            [
                'slug' => 'faq-locataire',
                'family' => 'general',
                'type' => 'faq',
                'title' => 'Locataire plutôt que propriétaire',
                'keywords' => 'locataire je loue propriétaire bailleur appartement loué pas chez moi',
                'body' => "Les travaux de rénovation relèvent du propriétaire du logement. Si la personne est locataire, le dire franchement et aimablement, et proposer d'en informer son propriétaire.",
            ],
            [
                'slug' => 'faq-copropriete',
                'family' => 'general',
                'type' => 'faq',
                'title' => 'Appartement en copropriété',
                'keywords' => 'copropriété syndic immeuble appartement assemblée générale parties communes',
                'body' => "En appartement, certains travaux dépendent de la copropriété et d'autres non. Cela se détermine au cas par cas avec un conseiller.",
            ],
            [
                'slug' => 'coverage-zone',
                'family' => 'general',
                'type' => 'coverage',
                'title' => 'Zone d\'intervention',
                'keywords' => 'zone département région vous intervenez où secteur chez moi',
                'body' => "Le code postal sert précisément à vérifier si un conseiller intervient dans le secteur. Si ce n'est pas le cas, le dire simplement plutôt que de faire patienter.",
            ],
            [
                'slug' => 'faq-delais',
                'family' => 'general',
                'type' => 'faq',
                'title' => 'Délais',
                'keywords' => 'quand délai combien de temps rapidement urgent cet hiver',
                'body' => 'Les délais dépendent du secteur et de la période. Aucune date ne doit être avancée par message ; le conseiller donnera une réponse fiable.',
                'never' => 'ne jamais s\'engager sur un délai',
            ],
        ];
    }
}
