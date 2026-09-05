import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Button, Card, Field, Input, Select, Table, Textarea } from '../../Components/Ui';
import { CAMPAIGN_STATUS_LABELS, CAMPAIGN_STATUS_STYLES, date, euro, num } from '../../lib/format';

export default function CampaignsIndex({ campaigns, filters, options, statuses }) {
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title="Campagnes" />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h1 className="text-lg font-semibold text-slate-900">Campagnes</h1>
                    <p className="text-xs text-slate-500">Le lien entre les créas et l'achat média.</p>
                </div>
                <Button onClick={() => setCreating((v) => !v)}>{creating ? 'Fermer' : '+ Nouvelle campagne'}</Button>
            </div>

            {creating && (
                <Card title="Nouvelle campagne" className="mb-4">
                    <CampaignForm options={options} statuses={statuses} onDone={() => setCreating(false)} />
                </Card>
            )}

            <div className="mb-4 flex flex-wrap gap-2">
                <Select className="w-40" value={filters.status ?? ''} onChange={(e) => router.get('/campaigns', { ...filters, status: e.target.value || undefined })}>
                    <option value="">Tous les statuts</option>
                    {statuses.map((status) => (
                        <option key={status} value={status}>
                            {CAMPAIGN_STATUS_LABELS[status]}
                        </option>
                    ))}
                </Select>
                <Select className="w-40" value={filters.product ?? ''} onChange={(e) => router.get('/campaigns', { ...filters, product: e.target.value || undefined })}>
                    <option value="">Tous les produits</option>
                    {options.products.map((product) => (
                        <option key={product.id} value={product.id}>
                            {product.name}
                        </option>
                    ))}
                </Select>
            </div>

            <Card bodyClassName="p-0">
                <Table
                    head={[
                        'Campagne',
                        'Produit',
                        'Canaux',
                        'Période',
                        { label: 'Budget', align: 'right' },
                        { label: 'Dépense', align: 'right' },
                        { label: 'Leads', align: 'right' },
                        { label: 'CPL', align: 'right' },
                        { label: 'Qualifiés', align: 'right' },
                        { label: 'Confirmés', align: 'right' },
                        'Statut',
                    ]}
                >
                    {campaigns.map((campaign) => (
                        <tr key={campaign.id} className="hover:bg-slate-50">
                            <td className="px-3 py-2.5">
                                <Link href={`/campaigns/${campaign.id}`} className="font-medium text-slate-800 hover:text-teal-700">
                                    {campaign.name}
                                </Link>
                                <span className="block text-[11px] text-slate-400">
                                    {campaign.creatives_count} créas · {campaign.objective ?? '—'}
                                </span>
                            </td>
                            <td className="px-3 py-2.5 text-xs">{campaign.product && <Badge color="teal">{campaign.product.code}</Badge>}</td>
                            <td className="px-3 py-2.5 text-xs text-slate-600">{campaign.channels.map((c) => c.code).join(' · ')}</td>
                            <td className="px-3 py-2.5 text-xs text-slate-600">
                                {date(campaign.start_date)} → {date(campaign.end_date)}
                            </td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{euro(campaign.budget)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{euro(campaign.metrics.spend)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{num(campaign.metrics.leads)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{euro(campaign.metrics.cpl)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{num(campaign.metrics.qualified_leads)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{num(campaign.metrics.confirmed)}</td>
                            <td className="px-3 py-2.5">
                                <span className={`inline-flex rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${CAMPAIGN_STATUS_STYLES[campaign.status]}`}>
                                    {CAMPAIGN_STATUS_LABELS[campaign.status]}
                                </span>
                            </td>
                        </tr>
                    ))}
                    {campaigns.length === 0 && (
                        <tr>
                            <td colSpan={11} className="px-6 py-10 text-center">
                                <p className="text-2xl" aria-hidden>
                                    📣
                                </p>
                                <p className="mt-1 text-sm font-medium text-slate-700">Aucune campagne</p>
                                <p className="mx-auto mt-1 max-w-md text-xs text-slate-500">
                                    Une campagne relie simplement des créas à un budget et à un canal. Elle sert à
                                    regrouper les performances — la créa reste l&apos;unité de base.
                                </p>
                            </td>
                        </tr>
                    )}
                </Table>
            </Card>
        </>
    );
}

export function CampaignForm({ campaign, options, statuses, onDone }) {
    const isEdit = !!campaign;
    const { data, setData, post, put, processing, errors } = useForm({
        name: campaign?.name ?? '',
        code: campaign?.code ?? '',
        product_id: campaign?.product?.id ?? '',
        country: campaign?.country ?? 'France',
        objective: campaign?.objective ?? '',
        start_date: campaign?.start_date ?? '',
        end_date: campaign?.end_date ?? '',
        budget: campaign?.budget ?? '',
        status: campaign?.status ?? 'draft',
        notes: campaign?.notes ?? '',
        channels: campaign?.channels?.map((c) => c.id) ?? [],
    });

    const submit = (event) => {
        event.preventDefault();
        isEdit ? put(`/campaigns/${campaign.id}`, { onSuccess: onDone }) : post('/campaigns', { onSuccess: onDone });
    };

    return (
        <form onSubmit={submit}>
            <div className="grid gap-3 sm:grid-cols-3">
                <Field label="Nom" error={errors.name} className="sm:col-span-2">
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="PAC France — Meta — Septembre Test 01" />
                </Field>
                <Field label="Code (utm_campaign)" error={errors.code}>
                    <Input value={data.code} onChange={(e) => setData('code', e.target.value)} placeholder="pac_france_meta_sept" />
                </Field>
                <Field label="Produit" error={errors.product_id}>
                    <Select value={data.product_id} onChange={(e) => setData('product_id', e.target.value)}>
                        <option value="">—</option>
                        {options.products.map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Pays" error={errors.country}>
                    <Input value={data.country} onChange={(e) => setData('country', e.target.value)} />
                </Field>
                <Field label="Objectif" error={errors.objective}>
                    <Input value={data.objective} onChange={(e) => setData('objective', e.target.value)} placeholder="Leads propriétaires qualifiés" />
                </Field>
                <Field label="Début" error={errors.start_date}>
                    <Input type="date" value={data.start_date ?? ''} onChange={(e) => setData('start_date', e.target.value)} />
                </Field>
                <Field label="Fin" error={errors.end_date}>
                    <Input type="date" value={data.end_date ?? ''} onChange={(e) => setData('end_date', e.target.value)} />
                </Field>
                <Field label="Budget (€)" error={errors.budget}>
                    <Input type="number" min="0" step="0.01" value={data.budget ?? ''} onChange={(e) => setData('budget', e.target.value)} />
                </Field>
                <Field label="Statut" error={errors.status}>
                    <Select value={data.status} onChange={(e) => setData('status', e.target.value)}>
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {CAMPAIGN_STATUS_LABELS[status]}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Notes" error={errors.notes} className="sm:col-span-3">
                    <Textarea rows={2} value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                </Field>
            </div>

            <div className="mt-3">
                <span className="mb-1 block text-xs font-medium text-slate-600">Canaux</span>
                <div className="flex flex-wrap gap-1.5">
                    {options.channels.map((channel) => {
                        const active = data.channels.map(String).includes(String(channel.id));
                        return (
                            <button
                                key={channel.id}
                                type="button"
                                onClick={() =>
                                    setData(
                                        'channels',
                                        active ? data.channels.filter((id) => String(id) !== String(channel.id)) : [...data.channels, channel.id],
                                    )
                                }
                                className={`rounded-lg px-2.5 py-1 text-xs ring-1 ring-inset transition ${
                                    active ? 'bg-teal-700 text-white ring-teal-700' : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50'
                                }`}
                            >
                                {active ? '✓ ' : ''}
                                {channel.name}
                            </button>
                        );
                    })}
                </div>
            </div>

            <Button type="submit" className="mt-3" disabled={processing}>
                {isEdit ? 'Enregistrer' : 'Créer la campagne'}
            </Button>
        </form>
    );
}
