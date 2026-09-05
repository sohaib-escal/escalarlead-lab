import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Button, Card, RatingBadge, StatCard, Table } from '../../Components/Ui';
import { CAMPAIGN_STATUS_LABELS, CAMPAIGN_STATUS_STYLES, date, euro, num, ratio } from '../../lib/format';
import { CampaignForm } from './Index';

export default function CampaignShow({ campaign, creatives, options, statuses }) {
    const [editing, setEditing] = useState(false);

    return (
        <>
            <Head title={campaign.name} />

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-lg font-semibold text-slate-900">{campaign.name}</h1>
                        <span className={`inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${CAMPAIGN_STATUS_STYLES[campaign.status]}`}>
                            {CAMPAIGN_STATUS_LABELS[campaign.status]}
                        </span>
                    </div>
                    <p className="text-xs text-slate-500">
                        {campaign.product?.name ?? '—'} · {campaign.country} · {campaign.channels.map((c) => c.name).join(', ')} ·{' '}
                        {date(campaign.start_date)} → {date(campaign.end_date)}
                    </p>
                    {campaign.objective && <p className="mt-1 text-xs text-slate-600">Objectif : {campaign.objective}</p>}
                </div>
                <div className="flex gap-2">
                    <Button variant="secondary" onClick={() => setEditing((v) => !v)}>
                        {editing ? 'Fermer' : 'Éditer'}
                    </Button>
                    <Button
                        variant="danger"
                        onClick={() => {
                            if (confirm('Supprimer cette campagne ?')) router.delete(`/campaigns/${campaign.id}`);
                        }}
                    >
                        Supprimer
                    </Button>
                </div>
            </div>

            {editing && (
                <Card title="Éditer la campagne" className="mb-4">
                    <CampaignForm campaign={campaign} options={options} statuses={statuses} onDone={() => setEditing(false)} />
                </Card>
            )}

            <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <StatCard label="Budget" value={euro(campaign.budget)} />
                <StatCard label="Dépense" value={euro(campaign.metrics.spend)} hint={campaign.budget ? `${Math.round((campaign.metrics.spend / campaign.budget) * 100)} % du budget` : null} />
                <StatCard label="CPL" value={euro(campaign.metrics.cpl)} />
                <StatCard label="Coût / qualifié" value={euro(campaign.metrics.cost_per_qualified)} tone="good" />
                <StatCard label="Coût / confirmé" value={euro(campaign.metrics.cost_per_confirmed)} tone="good" />
                <StatCard label="ROAS" value={ratio(campaign.metrics.roas)} />
            </div>

            {campaign.notes && (
                <Card title="Notes" className="mb-4">
                    <p className="whitespace-pre-line text-sm text-slate-700">{campaign.notes}</p>
                </Card>
            )}

            <Card title={`Créas de la campagne (${creatives.length})`} bodyClassName="p-0">
                <Table
                    head={[
                        'Créa',
                        'Audience',
                        'Statut',
                        { label: 'Dépense', align: 'right' },
                        { label: 'Leads', align: 'right' },
                        { label: 'CPL', align: 'right' },
                        { label: 'Coût / qualifié', align: 'right' },
                        'Perf.',
                    ]}
                >
                    {creatives.map((creative) => (
                        <tr key={creative.id} className="hover:bg-slate-50">
                            <td className="px-3 py-2.5">
                                <Link href={`/creatives/${creative.id}`} className="font-medium text-slate-800 hover:text-teal-700">
                                    {creative.reference}
                                </Link>
                                <p className="max-w-xs truncate text-[11px] text-slate-500">{creative.name}</p>
                            </td>
                            <td className="px-3 py-2.5">
                                <div className="flex max-w-xs flex-wrap gap-1">
                                    {creative.persona.slice(0, 4).map((chip) => (
                                        <span key={`${chip.category_slug}-${chip.value_id}`} className="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600">
                                            {chip.label}
                                        </span>
                                    ))}
                                </div>
                            </td>
                            <td className="px-3 py-2.5">{creative.status && <Badge color={creative.status.color}>{creative.status.name}</Badge>}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{euro(creative.metrics.spend)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{num(creative.metrics.leads)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{euro(creative.metrics.cpl)}</td>
                            <td className="px-3 py-2.5 text-right font-medium tabular-nums">{euro(creative.metrics.cost_per_qualified)}</td>
                            <td className="px-3 py-2.5">
                                <RatingBadge rating={creative.rating} manual={creative.rating_is_manual} />
                            </td>
                        </tr>
                    ))}
                    {creatives.length === 0 && (
                        <tr>
                            <td colSpan={8} className="px-6 py-10 text-center">
                                <p className="text-2xl" aria-hidden>
                                    🎨
                                </p>
                                <p className="mt-1 text-sm font-medium text-slate-700">Aucune créa dans cette campagne</p>
                                <p className="mx-auto mt-1 max-w-md text-xs text-slate-500">
                                    Ouvrez une créa et cochez cette campagne dans ses réglages : une même créa peut tourner
                                    dans plusieurs campagnes sans être dupliquée.
                                </p>
                                <Link href="/creatives" className="mt-3 inline-flex text-xs font-medium text-teal-700 hover:underline">
                                    Voir les créas →
                                </Link>
                            </td>
                        </tr>
                    )}
                </Table>
            </Card>
        </>
    );
}
