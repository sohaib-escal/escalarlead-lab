import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import CreativeCard from '../../Components/CreativeCard';
import FilterBar from '../../Components/FilterBar';
import VariationModal from '../../Components/VariationModal';
import { Badge, Button, Card, EmptyState, RatingBadge, Table } from '../../Components/Ui';
import { euro, num } from '../../lib/format';

export default function CreativesIndex({ creatives, filters, options }) {
    const [view, setView] = useState('cards');
    const [variationFor, setVariationFor] = useState(null);

    return (
        <>
            <Head title="Créas" />

            <header className="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">🎨 Créas</h1>
                    <p className="mt-0.5 text-sm text-slate-500">
                        {creatives.length} créa{creatives.length > 1 ? 's' : ''} · chaque carte est une hypothèse testée.
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <div className="flex rounded-xl bg-slate-100 p-0.5">
                        {[
                            ['cards', 'Cartes'],
                            ['table', 'Tableau'],
                        ].map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setView(key)}
                                className={`rounded-lg px-2.5 py-1 text-xs font-medium transition ${
                                    view === key ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    <Button as="link" href="/creatives/new">
                        + Nouvelle idée
                    </Button>
                </div>
            </header>

            <FilterBar url="/creatives" filters={filters} options={options} />

            {creatives.length === 0 ? (
                <EmptyState
                    title="Aucune créa ne correspond"
                    description="Élargissez les filtres, ou partez d'une branche non testée dans l'arbre."
                    action={
                        <Button as="link" href="/creative-tree" variant="secondary" size="sm" className="mt-2">
                            Ouvrir le Creative Tree
                        </Button>
                    }
                />
            ) : view === 'cards' ? (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {creatives.map((creative) => (
                        <CreativeCard key={creative.id} creative={creative} onVariation={setVariationFor} />
                    ))}
                </div>
            ) : (
                <Card bodyClassName="p-0">
                    <Table
                        head={[
                            'Créa',
                            'Audience',
                            'Canaux',
                            'Statut',
                            { label: 'Dépense', align: 'right' },
                            { label: 'CPL', align: 'right' },
                            { label: 'Coût / qualifié', align: 'right' },
                            'Perf.',
                        ]}
                    >
                        {creatives.map((creative) => (
                            <tr key={creative.id} className="align-top hover:bg-slate-50">
                                <td className="px-3 py-2.5">
                                    <Link href={`/creatives/${creative.id}`} className="font-medium text-slate-800 hover:text-teal-700">
                                        {creative.reference}
                                    </Link>
                                    <p className="max-w-xs truncate text-[11px] text-slate-500">{creative.name}</p>
                                </td>
                                <td className="px-3 py-2.5">
                                    <div className="flex max-w-xs flex-wrap gap-1">
                                        {creative.persona.slice(0, 4).map((chip) => (
                                            <span
                                                key={`${chip.category_slug}-${chip.value_id}`}
                                                className="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600"
                                            >
                                                {chip.label}
                                            </span>
                                        ))}
                                    </div>
                                </td>
                                <td className="px-3 py-2.5 text-xs text-slate-600">
                                    {creative.channels.map((c) => c.code).join(' · ') || '—'}
                                </td>
                                <td className="px-3 py-2.5">
                                    {creative.status && <Badge color={creative.status.color}>{creative.status.name}</Badge>}
                                </td>
                                <td className="px-3 py-2.5 text-right tabular-nums">{euro(creative.metrics.spend)}</td>
                                <td className="px-3 py-2.5 text-right tabular-nums">{euro(creative.metrics.cpl)}</td>
                                <td className="px-3 py-2.5 text-right font-medium tabular-nums">
                                    {euro(creative.metrics.cost_per_qualified)}
                                </td>
                                <td className="px-3 py-2.5">
                                    <RatingBadge rating={creative.rating} manual={creative.rating_is_manual} />
                                </td>
                            </tr>
                        ))}
                    </Table>
                </Card>
            )}

            {creatives.length > 0 && (
                <p className="mt-3 text-[11px] text-slate-400">
                    Total affiché : {num(creatives.reduce((sum, c) => sum + c.metrics.leads, 0))} leads ·{' '}
                    {num(creatives.reduce((sum, c) => sum + c.metrics.qualified_leads, 0))} qualifiés ·{' '}
                    {euro(creatives.reduce((sum, c) => sum + c.metrics.spend, 0))} dépensés
                </p>
            )}

            <VariationModal
                creative={variationFor}
                categories={options.categories}
                open={!!variationFor}
                onClose={() => setVariationFor(null)}
            />
        </>
    );
}
