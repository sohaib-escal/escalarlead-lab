import { Head, Link } from '@inertiajs/react';
import FilterBar from '../Components/FilterBar';
import { Card, RatingBadge, StatCard, Table } from '../Components/Ui';
import { euro, num, pct, ratio } from '../lib/format';

export default function Performance({ creatives, totals, byProduct, byChannel, filters, options }) {
    return (
        <>
            <Head title="Performance" />

            <div className="mb-4">
                <h1 className="text-lg font-semibold text-slate-900">Performance</h1>
                <p className="text-xs text-slate-500">
                    Un lead pas cher n'est pas forcément un bon lead : le coût par lead qualifié et par lead confirmé priment sur le CPL.
                </p>
            </div>

            <FilterBar url="/performance" filters={filters} options={options} />

            <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <StatCard label="Dépense" value={euro(totals.spend)} />
                <StatCard label="CPL" value={euro(totals.cpl)} hint={`${num(totals.leads)} leads`} />
                <StatCard label="Coût / qualifié" value={euro(totals.cost_per_qualified)} hint={`${num(totals.qualified_leads)} qualifiés`} tone="good" />
                <StatCard label="Coût / RDV" value={euro(totals.cost_per_appointment)} hint={`${num(totals.appointments)} RDV`} />
                <StatCard label="Coût / confirmé" value={euro(totals.cost_per_confirmed)} hint={`${num(totals.confirmed)} confirmés`} tone="good" />
                <StatCard label="Coût / vente" value={euro(totals.cost_per_sale)} hint={`${num(totals.sales)} ventes · ROAS ${ratio(totals.roas)}`} />
            </div>

            <div className="mb-4 grid gap-4 lg:grid-cols-2">
                <Breakdown title="Par produit" rows={byProduct} />
                <Breakdown title="Par canal" rows={byChannel} />
            </div>

            <Card title={`Créas avec données (${creatives.length})`} bodyClassName="p-0">
                <Table
                    head={[
                        'Créa',
                        { label: 'Dépense', align: 'right' },
                        { label: 'CTR', align: 'right' },
                        { label: 'Leads', align: 'right' },
                        { label: 'CPL', align: 'right' },
                        { label: 'Qualifiés', align: 'right' },
                        { label: 'Coût / qual.', align: 'right' },
                        { label: 'RDV', align: 'right' },
                        { label: 'Coût / RDV', align: 'right' },
                        { label: 'Confirmés', align: 'right' },
                        { label: 'Coût / conf.', align: 'right' },
                        { label: 'Ventes', align: 'right' },
                        { label: 'Coût / vente', align: 'right' },
                        'Perf.',
                    ]}
                >
                    {creatives.map((creative) => (
                        <tr key={creative.id} className="hover:bg-slate-50">
                            <td className="px-3 py-2.5">
                                <Link href={`/creatives/${creative.id}`} className="font-medium text-slate-800 hover:text-teal-700">
                                    {creative.reference}
                                </Link>
                                <p className="max-w-[14rem] truncate text-[11px] text-slate-500">{creative.name}</p>
                            </td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{euro(creative.metrics.spend)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{pct(creative.metrics.ctr)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{num(creative.metrics.leads)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{euro(creative.metrics.cpl)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{num(creative.metrics.qualified_leads)}</td>
                            <td className="px-3 py-2.5 text-right font-medium tabular-nums">{euro(creative.metrics.cost_per_qualified)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{num(creative.metrics.appointments)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{euro(creative.metrics.cost_per_appointment)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{num(creative.metrics.confirmed)}</td>
                            <td className="px-3 py-2.5 text-right font-medium tabular-nums">{euro(creative.metrics.cost_per_confirmed)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{num(creative.metrics.sales)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{euro(creative.metrics.cost_per_sale)}</td>
                            <td className="px-3 py-2.5">
                                <RatingBadge rating={creative.rating} manual={creative.rating_is_manual} />
                            </td>
                        </tr>
                    ))}
                    {creatives.length === 0 && (
                        <tr>
                            <td colSpan={14} className="px-6 py-10 text-center">
                                <p className="text-2xl" aria-hidden>
                                    📊
                                </p>
                                <p className="mt-1 text-sm font-medium text-slate-700">Aucune créa mesurée ici</p>
                                <p className="mx-auto mt-1 max-w-lg text-xs leading-relaxed text-slate-500">
                                    Les chiffres se saisissent à la main, sur la fiche d&apos;une créa (section
                                    Performance). Tant qu&apos;une créa n&apos;a pas de données, elle est « testée » mais
                                    pas jugée : elle n&apos;apparaît ni comme winner ni comme loser.
                                </p>
                                <Link href="/creatives" className="mt-3 inline-flex text-xs font-medium text-teal-700 hover:underline">
                                    Ouvrir une créa pour saisir ses chiffres →
                                </Link>
                            </td>
                        </tr>
                    )}
                </Table>
            </Card>
        </>
    );
}

function Breakdown({ title, rows }) {
    return (
        <Card title={title} bodyClassName="p-0">
            <Table
                head={[
                    '',
                    { label: 'Créas', align: 'right' },
                    { label: 'Dépense', align: 'right' },
                    { label: 'CPL', align: 'right' },
                    { label: 'Coût / qual.', align: 'right' },
                    { label: 'Coût / conf.', align: 'right' },
                ]}
            >
                {rows.map((row) => (
                    <tr key={row.label}>
                        <td className="px-3 py-2 font-medium text-slate-800">{row.label}</td>
                        <td className="px-3 py-2 text-right tabular-nums">{num(row.creatives)}</td>
                        <td className="px-3 py-2 text-right tabular-nums">{euro(row.metrics.spend)}</td>
                        <td className="px-3 py-2 text-right tabular-nums">{euro(row.metrics.cpl)}</td>
                        <td className="px-3 py-2 text-right font-medium tabular-nums">{euro(row.metrics.cost_per_qualified)}</td>
                        <td className="px-3 py-2 text-right tabular-nums">{euro(row.metrics.cost_per_confirmed)}</td>
                    </tr>
                ))}
                {rows.length === 0 && (
                    <tr>
                        <td colSpan={6} className="px-3 py-5 text-center text-xs text-slate-500">
                            Rien à comparer tant qu&apos;aucune performance n&apos;est saisie.
                        </td>
                    </tr>
                )}
            </Table>
        </Card>
    );
}
