import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Badge, Button, Card, CopyButton, Field, Input, Select, Textarea, Toggle } from '../../Components/Ui';

// Execution first: the idea was already decided in the wizard.
const STEPS = [
    { key: 'creative', label: '🎨 Créa' },
    { key: 'funnel', label: '🔗 Funnel & UTM' },
    { key: 'target', label: '👤 Ciblage' },
    { key: 'problem', label: '💸 Problème & angle' },
    { key: 'launch', label: '⚙️ Réglages' },
];

/** Client-side preview of the naming convention; the server guarantees uniqueness. */
function buildReference(data, options) {
    const parts = [];
    const product = options.products.find((p) => String(p.id) === String(data.product_id));
    if (product) parts.push(product.code);

    options.categories
        .filter((category) => category.in_naming)
        .forEach((category) => {
            const selected = (data.parameters[category.id] ?? []).filter(Boolean);
            selected.slice(0, 1).forEach((valueId) => {
                const value = category.values.find((v) => String(v.id) === String(valueId));
                if (value) parts.push(value.code);
            });
        });

    const channel = options.channels.find((c) => String(c.id) === String((data.channels ?? [])[0]));
    if (channel) parts.push(channel.code);

    return parts.length ? `${parts.join('-')}-001` : '';
}

export default function CreativeForm({ creative, preset, options, suggestedReference }) {
    const isEdit = !!creative;
    const [step, setStep] = useState(0);
    const stepKey = STEPS[step].key;
    const [autoName, setAutoName] = useState(!isEdit);

    const { data, setData, errors, processing } = useForm({
        reference: creative?.reference ?? suggestedReference ?? '',
        name: creative?.name ?? '',
        description: creative?.description ?? '',
        product_id: creative?.product?.id ?? preset?.product_id ?? '',
        creative_status_id: creative?.status?.id ?? options.statuses[0]?.id ?? '',
        landing_page_id: creative?.landing_page?.id ?? '',
        cta_option_id: creative?.cta?.id ?? '',
        format: creative?.format ?? 'static_image',
        asset_url: creative?.asset_url ?? '',
        asset_filename: creative?.asset_filename ?? '',
        thumbnail_url: creative?.thumbnail_url ?? '',
        asset: null,
        hook: creative?.hook ?? '',
        primary_text: creative?.primary_text ?? '',
        headline: creative?.headline ?? '',
        ad_description: creative?.ad_description ?? '',
        concept: creative?.concept ?? '',
        notes: creative?.notes ?? '',
        performance_override: creative?.rating_is_manual ? creative.rating : '',
        channels: creative?.channels?.map((c) => c.id) ?? preset?.channels ?? [],
        campaigns: creative?.campaigns?.map((c) => c.id) ?? [],
        parameters: creative?.parameter_selection ?? preset?.parameters ?? {},
        utm: {
            base_url: creative?.utm?.base_url ?? '',
            utm_source: creative?.utm?.utm_source ?? '',
            utm_medium: creative?.utm?.utm_medium ?? '',
            utm_campaign: creative?.utm?.utm_campaign ?? '',
            utm_content: creative?.utm?.utm_content ?? '',
            utm_term: creative?.utm?.utm_term ?? '',
            auto_sync: creative?.utm?.auto_sync ?? true,
        },
    });

    // `setData` deep-clones the form data, so arrays and objects get a fresh
    // identity on every keystroke. Effects below therefore depend on serialised
    // keys, never on the objects themselves, or they would loop forever.
    const channelKey = data.channels.join(',');
    const campaignKey = data.campaigns.join(',');
    const parameterKey = JSON.stringify(data.parameters);

    const suggestion = useMemo(
        () => buildReference(data, options),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [data.product_id, parameterKey, channelKey, options],
    );

    useEffect(() => {
        if (!isEdit && suggestion) setData('reference', suggestion);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [suggestion]);

    // Suggest UTM values from the selected channel / campaign / landing page.
    useEffect(() => {
        const channel = options.channels.find((c) => String(c.id) === String(data.channels[0]));
        const campaign = options.campaigns.find((c) => String(c.id) === String(data.campaigns[0]));
        const landingPage = options.landing_pages.find((p) => String(p.id) === String(data.landing_page_id));

        setData((current) => {
            if (!current.utm.auto_sync) return current;

            return {
                ...current,
                utm: {
                    ...current.utm,
                    base_url: current.utm.base_url || landingPage?.url || '',
                    utm_source: current.utm.utm_source || channel?.default_utm_source || '',
                    utm_medium: current.utm.utm_medium || channel?.default_utm_medium || '',
                    utm_campaign:
                        current.utm.utm_campaign ||
                        (campaign ? campaign.name.toLowerCase().replace(/[^a-z0-9]+/g, '_') : ''),
                    utm_content: (current.reference || '').toLowerCase(),
                },
            };
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [channelKey, campaignKey, data.landing_page_id, data.reference]);

    const selectedLabels = useMemo(() => {
        const labels = [];
        const parameters = JSON.parse(parameterKey);

        options.categories.forEach((category) => {
            (parameters[category.id] ?? []).filter(Boolean).forEach((valueId) => {
                const value = category.values.find((v) => String(v.id) === String(valueId));
                if (value) labels.push({ category: category.name, group: category.group, label: value.label });
            });
        });

        return labels;
    }, [parameterKey, options.categories]);

    // Suggest an internal name from the targeting until the buyer types their own.
    useEffect(() => {
        if (!autoName) return;

        const bits = selectedLabels
            .filter((l) => ['persona', 'problem'].includes(l.group))
            .slice(0, 4)
            .map((l) => l.label);
        const product = options.products.find((p) => String(p.id) === String(data.product_id));
        const name = [product?.name, ...bits].filter(Boolean).join(' — ');

        if (name) setData('name', name);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [parameterKey, data.product_id, autoName]);

    const setParameter = (category, valueId, checked = true) => {
        const current = (data.parameters[category.id] ?? []).filter(Boolean).map(String);
        let next;

        if (category.is_multi) {
            next = checked ? [...new Set([...current, String(valueId)])] : current.filter((v) => v !== String(valueId));
        } else {
            next = valueId ? [String(valueId)] : [];
        }

        setData('parameters', { ...data.parameters, [category.id]: next });
    };

    const toggleFromList = (key, id) => {
        const current = data[key].map(String);
        setData(key, current.includes(String(id)) ? current.filter((v) => v !== String(id)) : [...current, String(id)]);
    };

    const submit = (event) => {
        event.preventDefault();

        const payload = { ...data };
        if (!payload.asset) delete payload.asset;

        if (isEdit) {
            router.post(`/creatives/${creative.id}`, { ...payload, _method: 'put' }, { forceFormData: !!data.asset });
        } else {
            router.post('/creatives', payload, { forceFormData: !!data.asset });
        }
    };

    const groups = ['persona', 'property', 'financial', 'energy'];

    return (
        <form onSubmit={submit}>
            <Head title={isEdit ? `Éditer ${creative.reference}` : 'Nouvelle créa'} />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h1 className="text-lg font-semibold text-slate-900">
                        {isEdit ? `Exécution — ${creative.reference}` : 'Nouvelle créa'}
                    </h1>
                    <p className="text-xs text-slate-500">
                        Le hook, le visuel, la landing page et le tracking. Le ciblage reste modifiable plus bas.
                    </p>
                </div>
                <div className="flex gap-2">
                    {isEdit && (
                        <Button as="link" href={`/creatives/${creative.id}`} variant="secondary">
                            Annuler
                        </Button>
                    )}
                    <Button type="submit" disabled={processing}>
                        {isEdit ? 'Enregistrer' : 'Créer la créa'}
                    </Button>
                </div>
            </div>

            <nav className="mb-4 flex flex-wrap gap-1">
                {STEPS.map((s, index) => (
                    <button
                        key={s.key}
                        type="button"
                        onClick={() => setStep(index)}
                        className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                            index === step ? 'bg-teal-700 text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50'
                        }`}
                    >
                        <span className="mr-1 opacity-60">{index + 1}</span>
                        {s.label}
                    </button>
                ))}
            </nav>

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    {stepKey === 'target' && (
                        <>
                            <Card title="Produit & canaux">
                                <div className="grid gap-3 sm:grid-cols-2">
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
                                    <Field label="Format" error={errors.format}>
                                        <Select value={data.format} onChange={(e) => setData('format', e.target.value)}>
                                            {options.formats.map((format) => (
                                                <option key={format.value} value={format.value}>
                                                    {format.label}
                                                </option>
                                            ))}
                                        </Select>
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
                                                    onClick={() => toggleFromList('channels', channel.id)}
                                                    className={`rounded-lg px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition ${
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
                            </Card>

                            {groups.map((group) => {
                                const categories = options.categories.filter((c) => c.group === group);
                                if (categories.length === 0) return null;

                                return (
                                    <Card key={group} title={{ persona: 'Démographie', property: 'Logement', financial: 'Situation financière', energy: 'Énergie' }[group]}>
                                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            {categories.map((category) => (
                                                <ParameterPicker
                                                    key={category.id}
                                                    category={category}
                                                    data={data}
                                                    productId={data.product_id}
                                                    onChange={setParameter}
                                                />
                                            ))}
                                        </div>
                                    </Card>
                                );
                            })}
                        </>
                    )}

                    {stepKey === 'problem' && (
                        <Card title="Problème, déclencheur, motivation, objection">
                            <div className="grid gap-3 sm:grid-cols-2">
                                {options.categories
                                    .filter((c) => c.group === 'problem')
                                    .map((category) => (
                                        <ParameterPicker
                                            key={category.id}
                                            category={category}
                                            data={data}
                                            productId={data.product_id}
                                            onChange={setParameter}
                                        />
                                    ))}
                            </div>
                        </Card>
                    )}

                    {stepKey === 'creative' && (
                        <>
                            <Card title="Asset">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Field label="Lien de l'asset (Drive, Dropbox…)" error={errors.asset_url} className="sm:col-span-2">
                                        <Input value={data.asset_url} onChange={(e) => setData('asset_url', e.target.value)} placeholder="https://…" />
                                    </Field>
                                    <Field label="Nom du fichier" error={errors.asset_filename}>
                                        <Input value={data.asset_filename} onChange={(e) => setData('asset_filename', e.target.value)} />
                                    </Field>
                                    <Field label="Miniature (URL)" error={errors.thumbnail_url}>
                                        <Input value={data.thumbnail_url} onChange={(e) => setData('thumbnail_url', e.target.value)} />
                                    </Field>
                                    <Field label="Ou téléverser le fichier" hint="Image ou vidéo, 50 Mo max." error={errors.asset} className="sm:col-span-2">
                                        <input
                                            type="file"
                                            onChange={(e) => setData('asset', e.target.files[0] ?? null)}
                                            className="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-medium hover:file:bg-slate-200"
                                        />
                                    </Field>
                                </div>
                            </Card>

                            <Card title="Copy">
                                <Field label="Hook" error={errors.hook}>
                                    <Textarea rows={2} value={data.hook} onChange={(e) => setData('hook', e.target.value)} placeholder="Votre chaudière commence à vous coûter de plus en plus cher ?" />
                                </Field>
                                <Field label="Texte principal" error={errors.primary_text} className="mt-3">
                                    <Textarea rows={6} value={data.primary_text} onChange={(e) => setData('primary_text', e.target.value)} />
                                </Field>
                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                    <Field label="Titre" error={errors.headline}>
                                        <Input value={data.headline} onChange={(e) => setData('headline', e.target.value)} />
                                    </Field>
                                    <Field label="Description" error={errors.ad_description}>
                                        <Input value={data.ad_description} onChange={(e) => setData('ad_description', e.target.value)} />
                                    </Field>
                                    <Field label="CTA" error={errors.cta_option_id}>
                                        <Select value={data.cta_option_id} onChange={(e) => setData('cta_option_id', e.target.value)}>
                                            <option value="">—</option>
                                            {options.ctas.map((cta) => (
                                                <option key={cta.id} value={cta.id}>
                                                    {cta.label}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                </div>
                                <Field label="Concept créatif (ce que le visuel raconte)" error={errors.concept} className="mt-3">
                                    <Textarea rows={2} value={data.concept} onChange={(e) => setData('concept', e.target.value)} placeholder="Femme de 65 ans regardant sa facture à côté d'une vieille chaudière." />
                                </Field>
                            </Card>
                        </>
                    )}

                    {stepKey === 'funnel' && (
                        <>
                            <Card title="Landing page">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Field label="Landing page" error={errors.landing_page_id}>
                                        <Select value={data.landing_page_id} onChange={(e) => setData('landing_page_id', e.target.value)}>
                                            <option value="">—</option>
                                            {options.landing_pages.map((page) => (
                                                <option key={page.id} value={page.id}>
                                                    {page.name} ({page.version})
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <Field label="URL de base (surcharge)" error={errors['utm.base_url']}>
                                        <Input value={data.utm.base_url} onChange={(e) => setData('utm', { ...data.utm, base_url: e.target.value })} />
                                    </Field>
                                </div>
                            </Card>

                            <Card
                                title="UTM"
                                action={<Toggle checked={data.utm.auto_sync} onChange={(v) => setData('utm', { ...data.utm, auto_sync: v })} label="Génération auto" />}
                            >
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].map((key) => (
                                        <Field key={key} label={key} error={errors[`utm.${key}`]}>
                                            <Input value={data.utm[key] ?? ''} onChange={(e) => setData('utm', { ...data.utm, [key]: e.target.value })} />
                                        </Field>
                                    ))}
                                </div>
                                <FinalUrl utm={data.utm} landingPages={options.landing_pages} landingPageId={data.landing_page_id} />
                            </Card>
                        </>
                    )}

                    {stepKey === 'launch' && (
                        <Card title="Statut, campagnes et identification">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Statut" error={errors.creative_status_id}>
                                    <Select value={data.creative_status_id} onChange={(e) => setData('creative_status_id', e.target.value)}>
                                        {options.statuses.map((status) => (
                                            <option key={status.id} value={status.id}>
                                                {status.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label="ID de la créa" hint="Généré automatiquement, modifiable." error={errors.reference}>
                                    <Input value={data.reference} onChange={(e) => setData('reference', e.target.value)} />
                                </Field>
                                <Field label="Nom interne" error={errors.name} className="sm:col-span-2">
                                    <Input
                                        value={data.name}
                                        onChange={(e) => {
                                            setAutoName(false);
                                            setData('name', e.target.value);
                                        }}
                                    />
                                </Field>
                                <Field label="Description interne" error={errors.description} className="sm:col-span-2">
                                    <Textarea rows={2} value={data.description} onChange={(e) => setData('description', e.target.value)} />
                                </Field>
                                <Field label="Notes media buyer" error={errors.notes} className="sm:col-span-2">
                                    <Textarea rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                                </Field>
                            </div>

                            <div className="mt-3">
                                <span className="mb-1 block text-xs font-medium text-slate-600">Campagnes</span>
                                <div className="flex flex-wrap gap-1.5">
                                    {options.campaigns.map((campaign) => {
                                        const active = data.campaigns.map(String).includes(String(campaign.id));
                                        return (
                                            <button
                                                key={campaign.id}
                                                type="button"
                                                onClick={() => toggleFromList('campaigns', campaign.id)}
                                                className={`rounded-lg px-2.5 py-1 text-xs ring-1 ring-inset transition ${
                                                    active ? 'bg-teal-700 text-white ring-teal-700' : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50'
                                                }`}
                                            >
                                                {active ? '✓ ' : ''}
                                                {campaign.name}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        </Card>
                    )}

                    <div className="flex justify-between">
                        <Button type="button" variant="secondary" onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0}>
                            ← Précédent
                        </Button>
                        {step < STEPS.length - 1 ? (
                            <Button type="button" onClick={() => setStep((s) => Math.min(STEPS.length - 1, s + 1))}>
                                Suivant →
                            </Button>
                        ) : (
                            <Button type="submit" disabled={processing}>
                                {isEdit ? 'Enregistrer' : 'Créer la créa'}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="lg:sticky lg:top-20 lg:self-start">
                    <Card title="Aperçu de la créa">
                        <p className="font-mono text-sm font-semibold text-slate-900">{data.reference || 'ID généré à l\'enregistrement'}</p>
                        <p className="mt-0.5 text-xs text-slate-500">{data.name || 'Nom interne'}</p>

                        <Section label="Audience">
                            {selectedLabels.filter((l) => l.group !== 'problem').map((chip, i) => (
                                <Badge key={i} color="slate">
                                    {chip.label}
                                </Badge>
                            ))}
                        </Section>

                        <Section label="Problème & angle">
                            {selectedLabels.filter((l) => l.group === 'problem').map((chip, i) => (
                                <Badge key={i} color="teal">
                                    {chip.label}
                                </Badge>
                            ))}
                        </Section>

                        <Section label="Canaux">
                            {options.channels
                                .filter((c) => data.channels.map(String).includes(String(c.id)))
                                .map((channel) => (
                                    <Badge key={channel.id} color="sky">
                                        {channel.name}
                                    </Badge>
                                ))}
                        </Section>

                        {data.hook && (
                            <div className="mt-3 rounded-lg bg-slate-50 p-2.5">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Hook</p>
                                <p className="mt-0.5 text-sm text-slate-800">{data.hook}</p>
                            </div>
                        )}
                    </Card>

                    {isEdit && (
                        <p className="mt-3 text-center text-[11px] text-slate-400">
                            Version {creative.version} ·{' '}
                            <Link href={`/creatives/${creative.id}`} className="hover:underline">
                                voir la fiche
                            </Link>
                        </p>
                    )}
                </div>
            </div>
        </form>
    );
}

function Section({ label, children }) {
    const items = Array.isArray(children) ? children.filter(Boolean) : children;
    return (
        <div className="mt-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <div className="mt-1 flex flex-wrap gap-1">{items?.length ? items : <span className="text-xs text-slate-400">—</span>}</div>
        </div>
    );
}

function ParameterPicker({ category, data, productId, onChange }) {
    const selected = (data.parameters[category.id] ?? []).filter(Boolean).map(String);

    // A value scoped to another product would only create a meaningless creative.
    const values = category.values.filter(
        (value) => !value.product_id || !productId || String(value.product_id) === String(productId),
    );

    if (category.is_multi) {
        return (
            <div>
                <span className="mb-1 block text-xs font-medium text-slate-600">{category.name}</span>
                <div className="flex flex-wrap gap-1">
                    {values.map((value) => {
                        const active = selected.includes(String(value.id));
                        return (
                            <button
                                key={value.id}
                                type="button"
                                onClick={() => onChange(category, value.id, !active)}
                                className={`rounded-md px-2 py-1 text-[11px] ring-1 ring-inset transition ${
                                    active ? 'bg-teal-700 text-white ring-teal-700' : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50'
                                }`}
                            >
                                {value.label}
                            </button>
                        );
                    })}
                </div>
            </div>
        );
    }

    return (
        <Field label={category.name}>
            <Select value={selected[0] ?? ''} onChange={(e) => onChange(category, e.target.value)}>
                <option value="">—</option>
                {values.map((value) => (
                    <option key={value.id} value={value.id}>
                        {value.label}
                    </option>
                ))}
            </Select>
        </Field>
    );
}

function FinalUrl({ utm, landingPages, landingPageId }) {
    const base = utm.base_url || landingPages.find((p) => String(p.id) === String(landingPageId))?.url || '';
    const params = new URLSearchParams();
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach((key) => {
        if (utm[key]) params.append(key, utm[key]);
    });

    const url = base ? `${base}${params.toString() ? (base.includes('?') ? '&' : '?') + params.toString() : ''}` : '';

    return (
        <div className="mt-3 rounded-lg bg-slate-900 p-3">
            <div className="mb-1 flex items-center justify-between">
                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">URL de tracking finale</p>
                <CopyButton value={url} />
            </div>
            <p className="break-all font-mono text-xs text-emerald-300">{url || 'Sélectionnez une landing page'}</p>
        </div>
    );
}
