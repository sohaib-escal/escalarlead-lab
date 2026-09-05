import { Head, Link } from '@inertiajs/react';
import CreativeCard from '../Components/CreativeCard';
import { Card } from '../Components/Ui';
import { createUrlFor } from '../lib/branch';
import { CAMPAIGN_STATUS_LABELS, dateTime, euro, num } from '../lib/format';

const EVENT_ICONS = {
    created: '✨',
    updated: '✏️',
    status_changed: '🔁',
    archived: '📦',
    asset_uploaded: '🖼️',
    metrics_added: '📊',
    performance: '🏆',
    prompt_generated: '🤖',
    prompt_validated: '✅',
    generation_started: '🎬',
    generation_failed: '⚠️',
    generation_attached: '🎞️',
    brief: '📝',
};

/** One question: what should I do now? */
export default function Dashboard({
    cards,
    totals,
    opportunities,
    categoriesBySlug,
    recentWinners,
    activeCampaigns,
    activity,
    waiting,
    waitingCount,
    testedWithoutData,
}) {
    const queue = [
        ['prompts_to_review', '🤖', 'Prompts à relire', 'violet'],
        ['handoffs', '🎞️', 'À générer dans Flow', 'sky'],
        ['assets_to_promote', '✅', 'Assets à rattacher', 'teal'],
        ['generations_running', '⏳', 'Générations en cours', 'amber'],
    ].filter(([key]) => (waiting[key] ?? []).length > 0);

    return (
        <>
            <Head title="Aperçu" />

            <header className="mb-5">
                <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Que faire maintenant ?</h1>
                <p className="mt-0.5 text-sm text-slate-500">
                    Le travail se fait dans l&apos;arbre — ceci n&apos;est qu&apos;une file d&apos;attente.
                </p>
            </header>

            <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Tile icon="📥" value={waitingCount} label="en attente de vous" href="#queue" tone="bg-violet-50" />
                <Tile icon="⚠️" value={cards.opportunities} label="branches non testées" href="/creative-tree" tone="bg-amber-50" />
                <Tile icon="🔥" value={cards.winners} label="winners" href="/creatives?rating=winner" tone="bg-emerald-50" />
                <Tile icon="🎯" value={cards.ready} label="prêtes à lancer" href="/creatives" tone="bg-sky-50" />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="space-y-4 lg:col-span-2">
                    <div id="queue" className="rounded-2xl border border-slate-200 bg-white">
                        <header className="border-b border-slate-100 px-4 py-3">
                            <h2 className="text-sm font-semibold text-slate-800">📥 En attente de vous</h2>
                            <p className="text-[11px] text-slate-500">Du travail déjà lancé, bloqué sur une décision.</p>
                        </header>

                        {queue.length === 0 ? (
                            <div className="px-4 py-8 text-center">
                                <p className="text-2xl" aria-hidden>
                                    ✅
                                </p>
                                <p className="mt-1 text-sm font-medium text-slate-700">Rien ne vous attend</p>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Aucun prompt à relire, aucune génération en attente. Bon moment pour ouvrir une branche
                                    non testée.
                                </p>
                                <Link
                                    href="/creative-tree"
                                    className="mt-3 inline-flex rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-teal-800"
                                >
                                    Ouvrir le Creative Tree
                                </Link>
                            </div>
                        ) : (
                            <div className="divide-y divide-slate-100">
                                {queue.map(([key, icon, label]) => (
                                    <div key={key} className="px-4 py-3">
                                        <p className="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                            <span className="mr-1" aria-hidden>
                                                {icon}
                                            </span>
                                            {label} · {waiting[key].length}
                                        </p>
                                        <ul className="space-y-1">
                                            {waiting[key].slice(0, 5).map((item) => (
                                                <li key={`${key}-${item.id}`}>
                                                    <Link
                                                        href={`/creatives/${item.creative_id}`}
                                                        className="flex items-center gap-2 rounded-lg px-2 py-1 text-xs hover:bg-slate-50"
                                                    >
                                                        <span className="font-mono text-teal-700">{item.reference}</span>
                                                        <span className="text-slate-500">{item.label}</span>
                                                        <span className="ml-auto text-slate-300">→</span>
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="rounded-2xl border border-amber-200 bg-amber-50/50">
                        <header className="border-b border-amber-100 px-4 py-3">
                            <h2 className="text-sm font-semibold text-slate-800">🔥 À tester ensuite</h2>
                            <p className="text-[11px] text-slate-500">Jamais testées, juste à côté de ce qui marche.</p>
                        </header>
                        <ul className="divide-y divide-amber-100">
                            {opportunities.map((gap, index) => (
                                <li key={index} className="flex flex-wrap items-center gap-2 px-4 py-2.5">
                                    <span className="min-w-0 flex-1 text-xs text-slate-700">{gap.label}</span>
                                    <span className="text-[11px] text-slate-500">
                                        {gap.sibling_creatives} à côté
                                        {gap.sibling_winners > 0 && ` · 🏆 ${gap.sibling_winners}`}
                                    </span>
                                    <Link
                                        href={createUrlFor(gap.path, categoriesBySlug)}
                                        className="rounded-lg bg-teal-700 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-teal-800"
                                    >
                                        Créer
                                    </Link>
                                </li>
                            ))}
                            {opportunities.length === 0 && (
                                <li className="px-4 py-6 text-center text-xs text-slate-500">
                                    Toutes les branches de l&apos;arbre sont couvertes. Ajoutez un axe pour aller plus fin.
                                </li>
                            )}
                        </ul>
                    </div>

                    <div>
                        <h2 className="mb-2 text-sm font-semibold text-slate-800">🏆 Ce qui gagne</h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {recentWinners.map((creative) => (
                                <CreativeCard key={creative.id} creative={creative} />
                            ))}
                        </div>
                        {recentWinners.length === 0 && (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white/60 px-4 py-8 text-center">
                                <p className="text-sm font-medium text-slate-700">Pas encore de winner</p>
                                <p className="mx-auto mt-1 max-w-md text-xs text-slate-500">
                                    Une créa devient winner quand son coût par lead qualifié est bon — pas parce qu&apos;elle
                                    a été lancée. Saisissez des performances pour que le classement ait du sens.
                                </p>
                                <Link href="/performance" className="mt-3 inline-flex text-xs font-medium text-teal-700 hover:underline">
                                    Voir la performance →
                                </Link>
                            </div>
                        )}
                    </div>
                </section>

                <aside className="space-y-4">
                    {testedWithoutData > 0 && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-4">
                            <p className="text-sm font-medium text-slate-800">
                                {testedWithoutData} créa{testedWithoutData > 1 ? 's' : ''} en ligne sans données
                            </p>
                            <p className="mt-0.5 text-xs text-slate-500">
                                Lancée ne veut pas dire mesurée. Saisissez leurs chiffres pour pouvoir les comparer.
                            </p>
                            <Link href="/performance" className="mt-2 inline-flex text-xs font-medium text-teal-700 hover:underline">
                                Saisir des performances →
                            </Link>
                        </div>
                    )}

                    <Card title="Campagnes actives" bodyClassName="p-0">
                        <ul className="divide-y divide-slate-100">
                            {activeCampaigns.map((campaign) => (
                                <li key={campaign.id} className="px-4 py-2.5">
                                    <Link href={`/campaigns/${campaign.id}`} className="text-sm font-medium text-slate-800 hover:text-teal-700">
                                        {campaign.name}
                                    </Link>
                                    <p className="text-[11px] text-slate-500">
                                        {campaign.creatives_count} créas · {euro(campaign.metrics.spend)} ·{' '}
                                        {num(campaign.metrics.qualified_leads)} qualifiés ·{' '}
                                        {CAMPAIGN_STATUS_LABELS[campaign.status]}
                                    </p>
                                </li>
                            ))}
                            {activeCampaigns.length === 0 && (
                                <li className="px-4 py-6 text-center text-xs text-slate-500">
                                    Aucune campagne active. Une campagne relie simplement des créas à un budget.
                                </li>
                            )}
                        </ul>
                    </Card>

                    <Card title="Chiffres clés">
                        <div className="grid grid-cols-2 gap-3">
                            <Figure label="Dépense" value={euro(totals.spend)} />
                            <Figure label="Coût / qualifié" value={euro(totals.cost_per_qualified)} accent />
                            <Figure label="Coût / confirmé" value={euro(totals.cost_per_confirmed)} accent />
                            <Figure label="Ventes" value={num(totals.sales)} />
                        </div>
                    </Card>

                    <Card title="Activité" bodyClassName="p-0">
                        <ul className="max-h-72 divide-y divide-slate-100 overflow-y-auto">
                            {activity.map((entry) => (
                                <li key={entry.id} className="flex gap-2 px-4 py-2">
                                    <span aria-hidden>{EVENT_ICONS[entry.event] ?? '•'}</span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-xs text-slate-700">
                                            {entry.creative && (
                                                <Link href={`/creatives/${entry.creative.id}`} className="font-mono text-teal-700 hover:underline">
                                                    {entry.creative.reference}
                                                </Link>
                                            )}{' '}
                                            {entry.description}
                                        </p>
                                        <p className="text-[11px] text-slate-400">{dateTime(entry.created_at)}</p>
                                    </div>
                                </li>
                            ))}
                            {activity.length === 0 && (
                                <li className="px-4 py-6 text-center text-xs text-slate-500">Rien ne s&apos;est encore passé.</li>
                            )}
                        </ul>
                    </Card>
                </aside>
            </div>
        </>
    );
}

function Tile({ icon, value, label, href, tone }) {
    return (
        <Link
            href={href}
            className={`flex items-center gap-3 rounded-2xl border border-slate-200 ${tone} px-4 py-3 transition hover:-translate-y-0.5 hover:shadow-sm`}
        >
            <span className="text-2xl" aria-hidden>
                {icon}
            </span>
            <span>
                <span className="block text-2xl leading-none font-semibold tabular-nums text-slate-900">{value}</span>
                <span className="text-xs text-slate-600">{label}</span>
            </span>
        </Link>
    );
}

function Figure({ label, value, accent = false }) {
    return (
        <div>
            <p className="text-[11px] uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`text-lg font-semibold tabular-nums ${accent ? 'text-teal-700' : 'text-slate-900'}`}>{value}</p>
        </div>
    );
}
