import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AiPanel from '../../Components/AiPanel';
import VariationModal from '../../Components/VariationModal';
import { Badge, Button, Card, CopyButton, Field, Input, Metric, RatingBadge, Select, Table, Textarea } from '../../Components/Ui';
import { date, dateTime, euro, num, pct, ratio } from '../../lib/format';
import { iconFor } from '../../lib/icons';

export default function CreativeShow({ creative, options }) {
    const [showMetrics, setShowMetrics] = useState(false);
    const [showMetricForm, setShowMetricForm] = useState(false);
    const [variation, setVariation] = useState(false);

    const persona = creative.persona.filter((chip) => chip.group !== 'problem');
    const problem = creative.persona.filter((chip) => chip.group === 'problem');

    return (
        <>
            <Head title={creative.reference} />

            <header className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="font-mono text-lg font-semibold text-slate-900">{creative.reference}</h1>
                        {creative.status && <Badge color={creative.status.color}>{creative.status.name}</Badge>}
                        <RatingBadge rating={creative.rating} manual={creative.rating_is_manual} />
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {creative.name} · v{creative.version} · {creative.created_by ?? '—'} · {date(creative.created_at)}
                        {creative.duplicated_from && (
                            <>
                                {' · issue de '}
                                <Link href={`/creatives/${creative.duplicated_from.id}`} className="hover:underline">
                                    {creative.duplicated_from.reference}
                                </Link>
                            </>
                        )}
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Select
                        className="w-36"
                        value={creative.status?.id ?? ''}
                        onChange={(e) => router.put(`/creatives/${creative.id}/status`, { creative_status_id: e.target.value })}
                    >
                        {options.statuses.map((status) => (
                            <option key={status.id} value={status.id}>
                                {status.name}
                            </option>
                        ))}
                    </Select>
                    <Button variant="secondary" onClick={() => setVariation(true)}>
                        Créer une variation
                    </Button>
                    <Button as="link" href={`/creatives/${creative.id}/edit`}>
                        Éditer l&apos;exécution
                    </Button>
                    <Button
                        variant="ghost"
                        onClick={() => {
                            if (confirm('Archiver cette créa ? Elle sort des vues actives, rien n\'est perdu.')) {
                                router.delete(`/creatives/${creative.id}`);
                            }
                        }}
                    >
                        Archiver
                    </Button>
                </div>
            </header>

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <Card title="💡 L'idée">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Chips label="Audience" chips={persona} color="slate" />
                            <Chips label="Problème & angle" chips={problem} color="teal" />
                        </div>
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <Info label="Produit" value={creative.product?.name} />
                            <Info label="Format" value={creative.format_label} />
                            <Info label="Canaux" value={creative.channels.map((c) => c.name).join(', ')} />
                            <Info
                                label="Campagnes"
                                value={
                                    creative.campaigns.length ? (
                                        <span className="flex flex-wrap gap-1">
                                            {creative.campaigns.map((campaign) => (
                                                <Link key={campaign.id} href={`/campaigns/${campaign.id}`} className="text-teal-700 hover:underline">
                                                    {campaign.name}
                                                </Link>
                                            ))}
                                        </span>
                                    ) : null
                                }
                            />
                            <Info label="CTA" value={creative.cta?.label} />
                            <Info label="Landing page" value={creative.landing_page?.name} />
                        </div>
                    </Card>

                    <AiPanel creative={creative} options={options} />

                    <Card title="🎨 Exécution">
                        <Copy label="Hook" value={creative.hook} big />
                        <Copy label="Texte principal" value={creative.primary_text} multiline />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Copy label="Titre" value={creative.headline} />
                            <Copy label="Description" value={creative.ad_description} />
                        </div>
                        <Copy label="Concept créatif" value={creative.concept} />

                        <div className="mt-4 grid gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2">
                            <Info label="Fichier" value={creative.asset_filename} />
                            <Info
                                label="Source de l'asset"
                                value={
                                    creative.asset_source
                                        ? { google_veo: 'Google Veo', google_flow: 'Google Flow', upload: 'Téléversé', link: 'Lien' }[
                                              creative.asset_source
                                          ] ?? creative.asset_source
                                        : null
                                }
                            />
                            <Info
                                label="Lien"
                                value={
                                    creative.asset_url ? (
                                        <a href={creative.asset_url} target="_blank" rel="noreferrer" className="break-all text-teal-700 hover:underline">
                                            {creative.asset_url}
                                        </a>
                                    ) : null
                                }
                            />
                        </div>

                        {creative.asset_provenance && (
                            <div className="mt-3 rounded-xl bg-slate-50 p-3">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    Provenance de l&apos;asset
                                </p>
                                <p className="mt-0.5 text-xs text-slate-700">
                                    {creative.asset_provenance.label}
                                    {creative.asset_provenance.model && ` · ${creative.asset_provenance.model}`}
                                    {creative.asset_provenance.prompt_version &&
                                        ` · prompt v${creative.asset_provenance.prompt_version}`}
                                    {creative.asset_provenance.prompt_model && ` (${creative.asset_provenance.prompt_model})`}
                                    {creative.asset_provenance.generated_at && ` · ${creative.asset_provenance.generated_at}`}
                                </p>
                                {creative.asset_provenance.generation_id && (
                                    <p className="mt-0.5 font-mono text-[11px] text-slate-400">
                                        génération #{creative.asset_provenance.generation_id}
                                        {creative.asset_provenance.external_id && ` · ${creative.asset_provenance.external_id}`}
                                    </p>
                                )}
                            </div>
                        )}

                        {creative.asset_public_url && (
                            <div className="mt-3">
                                {creative.asset_mime?.startsWith('video') ? (
                                    <video src={creative.asset_public_url} controls className="max-h-72 rounded-xl ring-1 ring-slate-200" />
                                ) : (
                                    <img src={creative.asset_public_url} alt="" className="max-h-64 rounded-xl ring-1 ring-slate-200" />
                                )}
                            </div>
                        )}
                    </Card>

                    <Card title="🔗 Funnel & tracking">
                        <div className="grid gap-x-4 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                            {['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].map((key) => (
                                <div key={key} className="flex items-baseline justify-between gap-2 border-b border-dashed border-slate-100 py-1">
                                    <span className="font-mono text-[11px] text-slate-500">{key}</span>
                                    <span className="truncate font-mono text-xs text-slate-800">{creative.utm[key] || '—'}</span>
                                </div>
                            ))}
                        </div>

                        <div className="mt-3 rounded-xl bg-slate-900 p-3">
                            <div className="mb-1 flex items-center justify-between">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">URL de tracking</p>
                                <CopyButton value={creative.utm.final_url} label="Copier" />
                            </div>
                            <p className="break-all font-mono text-xs text-emerald-300">{creative.utm.final_url ?? '—'}</p>
                        </div>
                    </Card>

                    <Card
                        title="📊 Performance"
                        action={
                            <div className="flex gap-2">
                                <Button size="sm" variant="ghost" onClick={() => setShowMetrics((v) => !v)}>
                                    {showMetrics ? 'Réduire' : 'Détail'}
                                </Button>
                                <Button size="sm" variant="secondary" onClick={() => setShowMetricForm((v) => !v)}>
                                    {showMetricForm ? 'Fermer' : '+ Saisir'}
                                </Button>
                            </div>
                        }
                    >
                        {!creative.has_performance && (
                            <p className="mb-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                Aucune donnée saisie pour l&apos;instant. Cette créa a peut-être été lancée, mais elle
                                n&apos;est pas encore mesurée — elle ne peut donc ni gagner ni perdre.
                            </p>
                        )}

                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <Headline label="Dépense" value={euro(creative.totals.spend)} />
                            <Headline label="Leads qualifiés" value={num(creative.totals.qualified_leads)} />
                            <Headline label="Coût / qualifié" value={euro(creative.totals.cost_per_qualified)} accent />
                            <Headline label="Coût / confirmé" value={euro(creative.totals.cost_per_confirmed)} accent />
                        </div>

                        {showMetrics && (
                            <div className="mt-4 grid gap-x-6 sm:grid-cols-3">
                                <div>
                                    <Metric label="Impressions" value={num(creative.totals.impressions)} />
                                    <Metric label="Reach" value={num(creative.totals.reach)} />
                                    <Metric label="Clics" value={num(creative.totals.clicks)} />
                                    <Metric label="CTR" value={pct(creative.totals.ctr)} />
                                    <Metric label="CPC" value={euro(creative.totals.cpc)} />
                                    <Metric label="CPM" value={euro(creative.totals.cpm)} />
                                </div>
                                <div>
                                    <Metric label="Leads" value={num(creative.totals.leads)} />
                                    <Metric label="CPL" value={euro(creative.totals.cpl)} />
                                    <Metric label="Taux de qualification" value={pct(creative.totals.qualification_rate)} />
                                    <Metric label="Contactés" value={num(creative.totals.contacted)} />
                                    <Metric label="Qualifiés téléphone" value={num(creative.totals.phone_qualified)} />
                                </div>
                                <div>
                                    <Metric label="RDV" value={num(creative.totals.appointments)} />
                                    <Metric label="Coût / RDV" value={euro(creative.totals.cost_per_appointment)} />
                                    <Metric label="Confirmés" value={num(creative.totals.confirmed)} />
                                    <Metric label="Ventes" value={num(creative.totals.sales)} />
                                    <Metric label="Coût / vente" value={euro(creative.totals.cost_per_sale)} />
                                    <Metric label="ROAS" value={ratio(creative.totals.roas)} />
                                </div>
                            </div>
                        )}

                        {showMetricForm && <MetricForm creative={creative} options={options} onDone={() => setShowMetricForm(false)} />}

                        {showMetrics && creative.metric_rows.length > 0 && (
                            <div className="mt-4">
                                <Table
                                    head={[
                                        'Période',
                                        'Campagne',
                                        { label: 'Dépense', align: 'right' },
                                        { label: 'Leads', align: 'right' },
                                        { label: 'Qualifiés', align: 'right' },
                                        { label: 'RDV', align: 'right' },
                                        { label: 'Confirmés', align: 'right' },
                                        '',
                                    ]}
                                >
                                    {creative.metric_rows.map((row) => (
                                        <tr key={row.id}>
                                            <td className="px-3 py-2 text-xs">
                                                {date(row.period_start)} → {date(row.period_end)}
                                            </td>
                                            <td className="px-3 py-2 text-xs text-slate-600">{row.campaign ?? '—'}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{euro(row.spend)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{num(row.leads)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{num(row.qualified_leads)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{num(row.appointments)}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{num(row.confirmed)}</td>
                                            <td className="px-3 py-2 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => router.delete(`/metrics/${row.id}`)}
                                                    className="text-[11px] text-slate-400 hover:text-rose-600"
                                                >
                                                    Suppr.
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            </div>
                        )}
                    </Card>
                </div>

                <div className="space-y-4">
                    <Card title="Indicateur">
                        <div className="flex items-center gap-2">
                            <RatingBadge rating={creative.rating} manual={creative.rating_is_manual} />
                            <span className="text-[11px] text-slate-500">
                                {creative.rating_is_manual ? 'forcé' : 'calculé sur le coût / lead qualifié'}
                            </span>
                        </div>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {['winner', 'promising', 'average', 'poor'].map((value) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => router.put(`/creatives/${creative.id}/rating`, { performance_override: value })}
                                    className="rounded-lg px-2 py-1 text-[11px] text-slate-600 ring-1 ring-inset ring-slate-300 hover:bg-slate-50"
                                >
                                    {value}
                                </button>
                            ))}
                            <button
                                type="button"
                                onClick={() => router.put(`/creatives/${creative.id}/rating`, { performance_override: null })}
                                className="rounded-lg px-2 py-1 text-[11px] text-slate-400 hover:bg-slate-50"
                            >
                                auto
                            </button>
                        </div>
                    </Card>

                    <NotesCard creative={creative} />

                    <Card title="Historique" bodyClassName="p-0">
                        <ol className="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                            {creative.history.map((entry) => (
                                <li key={entry.id} className="px-4 py-2">
                                    <p className="text-xs text-slate-700">{entry.description}</p>
                                    <p className="text-[11px] text-slate-400">
                                        {dateTime(entry.created_at)} · {entry.author ?? 'système'}
                                    </p>
                                </li>
                            ))}
                            {creative.history.length === 0 && <li className="px-4 py-5 text-center text-xs text-slate-500">Rien encore.</li>}
                        </ol>
                    </Card>
                </div>
            </div>

            <VariationModal
                creative={variation ? creative : null}
                categories={options.categories}
                open={variation}
                onClose={() => setVariation(false)}
            />
        </>
    );
}

function Headline({ label, value, accent = false }) {
    return (
        <div className="rounded-xl bg-slate-50 px-3 py-2">
            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`text-lg font-semibold tabular-nums ${accent ? 'text-teal-700' : 'text-slate-900'}`}>{value}</p>
        </div>
    );
}

function Chips({ label, chips, color }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <div className="mt-1 flex flex-wrap gap-1">
                {chips.length ? (
                    chips.map((chip) => (
                        <Badge key={`${chip.category_slug}-${chip.value_id}`} color={color}>
                            <span className="mr-1" aria-hidden>
                                {iconFor(chip.category_slug, chip.code)}
                            </span>
                            {chip.label}
                        </Badge>
                    ))
                ) : (
                    <span className="text-xs text-slate-400">—</span>
                )}
            </div>
        </div>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className="text-sm text-slate-800">{value || '—'}</p>
        </div>
    );
}

function Copy({ label, value, big = false, multiline = false }) {
    return (
        <div className="mb-3 last:mb-0">
            <div className="flex items-center justify-between">
                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
                {value && <CopyButton value={value} />}
            </div>
            <p className={`mt-0.5 text-slate-800 ${big ? 'text-base font-medium' : 'text-sm'} ${multiline ? 'whitespace-pre-line' : ''}`}>
                {value || '—'}
            </p>
        </div>
    );
}

function NotesCard({ creative }) {
    const { data, setData, post, processing, reset } = useForm({ body: '' });

    return (
        <Card title="Notes">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(`/creatives/${creative.id}/notes`, { onSuccess: () => reset('body') });
                }}
            >
                <Textarea rows={2} value={data.body} onChange={(e) => setData('body', e.target.value)} placeholder="Ajouter une note…" />
                <Button type="submit" size="sm" className="mt-2" disabled={processing || !data.body}>
                    Ajouter
                </Button>
            </form>

            <ul className="mt-3 space-y-2">
                {creative.note_entries.map((note) => (
                    <li key={note.id} className="rounded-xl bg-slate-50 p-2">
                        <p className="text-xs text-slate-700">{note.body}</p>
                        <p className="mt-0.5 text-[11px] text-slate-400">
                            {note.author ?? '—'} · {dateTime(note.created_at)}
                        </p>
                    </li>
                ))}
                {creative.note_entries.length === 0 && <li className="text-xs text-slate-400">Aucune note.</li>}
            </ul>
        </Card>
    );
}

function MetricForm({ creative, options, onDone }) {
    const { data, setData, post, processing, errors } = useForm({
        campaign_id: creative.campaigns[0]?.id ?? '',
        channel_id: creative.channels[0]?.id ?? '',
        period_start: '',
        period_end: '',
        spend: 0,
        impressions: 0,
        reach: 0,
        clicks: 0,
        leads: 0,
        qualified_leads: 0,
        contacted: 0,
        phone_qualified: 0,
        appointments: 0,
        confirmed: 0,
        sales: 0,
        revenue: 0,
        notes: '',
    });

    const numeric = [
        ['spend', 'Dépense (€)'],
        ['impressions', 'Impressions'],
        ['reach', 'Reach'],
        ['clicks', 'Clics'],
        ['leads', 'Leads'],
        ['qualified_leads', 'Leads qualifiés'],
        ['contacted', 'Contactés'],
        ['phone_qualified', 'Qualifiés téléphone'],
        ['appointments', 'RDV'],
        ['confirmed', 'Confirmés'],
        ['sales', 'Ventes'],
        ['revenue', 'CA (€)'],
    ];

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(`/creatives/${creative.id}/metrics`, { onSuccess: onDone });
            }}
            className="mt-4 rounded-xl bg-slate-50 p-3"
        >
            <div className="grid gap-3 sm:grid-cols-4">
                <Field label="Du" error={errors.period_start}>
                    <Input type="date" value={data.period_start} onChange={(e) => setData('period_start', e.target.value)} />
                </Field>
                <Field label="Au" error={errors.period_end}>
                    <Input type="date" value={data.period_end} onChange={(e) => setData('period_end', e.target.value)} />
                </Field>
                <Field label="Campagne">
                    <Select value={data.campaign_id} onChange={(e) => setData('campaign_id', e.target.value)}>
                        <option value="">—</option>
                        {options.campaigns.map((campaign) => (
                            <option key={campaign.id} value={campaign.id}>
                                {campaign.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Canal">
                    <Select value={data.channel_id} onChange={(e) => setData('channel_id', e.target.value)}>
                        <option value="">—</option>
                        {options.channels.map((channel) => (
                            <option key={channel.id} value={channel.id}>
                                {channel.name}
                            </option>
                        ))}
                    </Select>
                </Field>

                {numeric.map(([key, label]) => (
                    <Field key={key} label={label} error={errors[key]}>
                        <Input
                            type="number"
                            min="0"
                            step={key === 'spend' || key === 'revenue' ? '0.01' : '1'}
                            value={data[key]}
                            onChange={(e) => setData(key, e.target.value)}
                        />
                    </Field>
                ))}
            </div>

            <Button type="submit" className="mt-3" disabled={processing}>
                Enregistrer
            </Button>
        </form>
    );
}
