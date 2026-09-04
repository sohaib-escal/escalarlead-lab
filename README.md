# Creative Tree — Rénovation France

Internal **creative intelligence + campaign tracking** tool for a media-buying team generating
home-renovation leads in France (pompe à chaleur, panneaux solaires, double vitrage).

It is *not* an ad platform: nothing connects to Meta, Google or TikTok. It is the team's source of
truth for **what we are testing, who we target, what problem we solve, where it runs, what happened,
and what to test next.**

## Stack

Laravel 13 · PostgreSQL · Inertia.js · React 19 · Tailwind CSS v4 · Vite.

## Setup

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
createdb fr_renovation_creative_os          # PostgreSQL
php artisan migrate --seed                  # schema + reference data + demo data
npm run build                               # or: npm run dev
php artisan serve --port=8321
```

Demo accounts (password `password`):

| Rôle        | Email                    | Peut faire                                              |
|-------------|--------------------------|---------------------------------------------------------|
| Admin       | `admin@renovation.fr`    | tout, dont `/admin` (paramètres, produits, utilisateurs) |
| Media buyer | `media@renovation.fr`    | créas, campagnes, landing pages, performances            |
| Creative    | `studio@renovation.fr`   | consulter les briefs, éditer la créa et la copy          |

## The five ideas the app is built on

1. **The Creative Tree is the heart.** Creatives are grouped along a configurable list of axes
   (product → specific problem → gender → age → motivation → channel by default). Every node shows
   `n créas · n live · n winners · coût par lead qualifié`, and every *missing* child is shown as
   `⚠ non testé` with a **Créer une créa** button that deep-links to a pre-filled form. The tree is
   the testing roadmap, not just a report.
2. **The creative record is the atomic unit.** One record carries the persona, the problem, the
   angle, the copy, the asset, the landing page, the UTM and the history. It can belong to several
   campaigns without being duplicated.
3. **Targeting is relational, never a JSON blob.** `creative_parameters` links a creative to
   `parameter_values`, so "toutes les créas PAC ciblant les femmes 60–69 avec une facture de
   chauffage élevée" is a real query (`Creative::withAllParameterValues([...])`).
4. **The tree is data-driven.** Categories and values live in `parameter_categories` /
   `parameter_values` and are managed from `/admin`. Two flags drive everything: `in_tree` (usable
   as a tree axis) and `in_naming` (contributes to the generated creative ID). Adding "Type de
   chaudière" as a new dimension takes no code change.
5. **A cheap lead is not a good lead.** The automatic performance rating is computed from the
   **cost per qualified lead**, not the CPL, and only once a creative has spent enough
   (`config/creative.php`). A media buyer can always override it manually.

## Screens

`/login` · `/dashboard` · `/creative-tree` · `/creatives` · `/creatives/new` · `/creatives/{id}`
`/campaigns` · `/campaigns/{id}` · `/landing-pages` · `/performance` · `/admin`

## Naming convention

Creative IDs are generated from the product code, the values of the categories flagged `in_naming`,
the first channel, and a sequence number — then made unique on save. Always editable.

```
PAC-W-60-69-HIGHBILL-AID-FB-001
SOLAR-M-50-59-ELECBILL-INDEP-GGL-010
```

## UTM

Each creative has one UTM row. Values are suggested from the channel defaults, the campaign code and
the creative ID, stay editable, and produce the final tracking URL with a copy button:

```
https://renovation-france.fr/diagnostic-pompe-a-chaleur
  ?utm_source=facebook&utm_medium=paid_social
  &utm_campaign=pac_france_aides&utm_content=pac-w-60-69-highbill-aid-fb-001
```

## Performance

Metrics are entered by hand (no API integrations in V1) per creative and per period: spend,
impressions, reach, clicks, leads, qualified leads, call-centre outcomes (contacted, phone
qualified, appointments, field-confirmed), sales and revenue. Everything else — CTR, CPC, CPM, CPL,
cost per qualified / appointment / confirmed / sale, ROAS — is derived in `App\Support\MetricsSummary`.

## Tests

```bash
php artisan test          # 28 tests — uses the fr_renovation_creative_os_test database
./vendor/bin/pint --test  # code style
npm run build             # frontend build
```

Create the test database once with `createdb fr_renovation_creative_os_test`.

## Deliberately not in V1

Meta/Google/TikTok APIs, automatic campaign creation or ad publishing, AI creative generation,
billing, CRM, call-centre software, attribution modelling, multi-tenant SaaS, complex permissions.
The schema is shaped so those can be added later without a rewrite.
# escalarlead-lab
