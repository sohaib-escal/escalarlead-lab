import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '../../Components/Ui';
import { iconFor } from '../../lib/icons';

/**
 * Layer 1 — the creative idea.
 *
 * Four quick choices and a summary. No copy, no asset, no UTM: the execution
 * belongs to the creative record, not to this hypothesis.
 */
const STEPS = [
    { key: 'product', title: 'Quel produit ?', hint: 'Ce que l\'on vend.' },
    { key: 'problem', title: 'Quel problème ?', hint: 'Ce que la personne vit, concrètement.' },
    { key: 'audience', title: 'Qui exactement ?', hint: 'Plus c\'est précis, plus la créa est juste.' },
    { key: 'angle', title: 'Quel angle ?', hint: 'Ce qui doit déclencher le clic.' },
    { key: 'outcome', title: 'Ce que l\'on cherche à provoquer', hint: 'Relisez avant de créer.' },
];

const AUDIENCE_SLUGS = ['gender', 'age', 'property-type', 'homeowner', 'household', 'income'];
const ANGLE_SLUGS = ['motivation', 'trigger', 'objection'];

export default function CreativeNew({ preset, options, suggestedReference }) {
    const [step, setStep] = useState(0);
    const [productId, setProductId] = useState(preset?.product_id ?? '');
    const [channels, setChannels] = useState((preset?.channels ?? []).map(String));
    const [parameters, setParameters] = useState(() => {
        const initial = {};
        Object.entries(preset?.parameters ?? {}).forEach(([categoryId, values]) => {
            initial[categoryId] = values.map(String);
        });
        return initial;
    });
    const [saving, setSaving] = useState(false);

    const categoryBySlug = useMemo(
        () => Object.fromEntries(options.categories.map((category) => [category.slug, category])),
        [options.categories],
    );

    const setValue = (category, valueId, multi = false) => {
        setParameters((current) => {
            const existing = (current[category.id] ?? []).map(String);
            let next;

            if (multi) {
                next = existing.includes(String(valueId))
                    ? existing.filter((v) => v !== String(valueId))
                    : [...existing, String(valueId)];
            } else {
                next = existing.includes(String(valueId)) ? [] : [String(valueId)];
            }

            return { ...current, [category.id]: next };
        });
    };

    const selected = (category, valueId) => (parameters[category?.id] ?? []).map(String).includes(String(valueId));

    const labelsFor = (slugs) =>
        slugs.flatMap((slug) => {
            const category = categoryBySlug[slug];
            if (!category) return [];
            return (parameters[category.id] ?? [])
                .map((valueId) => category.values.find((v) => String(v.id) === String(valueId)))
                .filter(Boolean)
                .map((value) => ({ ...value, slug, categoryName: category.name }));
        });

    const product = options.products.find((p) => String(p.id) === String(productId));
    const problems = labelsFor(['specific-problem', 'problem']);
    const audience = labelsFor(AUDIENCE_SLUGS);
    const angle = labelsFor(ANGLE_SLUGS);

    const suggestedName = [product?.name, ...audience.slice(0, 2).map((a) => a.label), problems[0]?.label]
        .filter(Boolean)
        .join(' — ');

    const canContinue = {
        0: !!productId,
        1: problems.length > 0,
        2: audience.length > 0,
        3: angle.length > 0,
        4: true,
    }[step];

    const submit = () => {
        setSaving(true);
        router.post(
            '/creatives',
            {
                name: suggestedName || 'Nouvelle idée',
                product_id: productId,
                creative_status_id: options.statuses.find((s) => s.slug === 'idea')?.id ?? options.statuses[0]?.id,
                format: 'video',
                channels,
                parameters,
                utm: { auto_sync: true },
            },
            { onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title="Nouvelle idée" />

            <div className="mx-auto max-w-4xl">
                <header className="mb-5">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-teal-700">Idée créative</p>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{STEPS[step].title}</h1>
                    <p className="mt-0.5 text-sm text-slate-500">{STEPS[step].hint}</p>
                </header>

                <ol className="mb-6 flex gap-1.5">
                    {STEPS.map((s, index) => (
                        <li key={s.key} className="flex-1">
                            <button
                                type="button"
                                onClick={() => index <= step && setStep(index)}
                                className={`h-1.5 w-full rounded-full transition ${
                                    index <= step ? 'bg-teal-600' : 'bg-slate-200'
                                }`}
                                aria-label={s.title}
                            />
                        </li>
                    ))}
                </ol>

                {step === 0 && (
                    <CardGrid>
                        {options.products.map((item) => (
                            <PickCard
                                key={item.id}
                                icon={iconFor('product', item.code)}
                                label={item.name}
                                sublabel={item.code}
                                active={String(productId) === String(item.id)}
                                onClick={() => setProductId(item.id)}
                            />
                        ))}
                    </CardGrid>
                )}

                {step === 1 && categoryBySlug['specific-problem'] && (
                    <CardGrid>
                        {/* Only the problems that make sense for the chosen product. */}
                        {categoryBySlug['specific-problem'].values
                            .filter((value) => !value.product_id || String(value.product_id) === String(productId))
                            .map((value) => (
                            <PickCard
                                key={value.id}
                                icon={iconFor('specific-problem', value.code)}
                                label={value.label}
                                active={selected(categoryBySlug['specific-problem'], value.id)}
                                onClick={() => setValue(categoryBySlug['specific-problem'], value.id)}
                            />
                            ))}
                    </CardGrid>
                )}

                {step === 2 && (
                    <div className="space-y-5">
                        {AUDIENCE_SLUGS.map((slug) => {
                            const category = categoryBySlug[slug];
                            if (!category) return null;

                            return (
                                <ChipRow
                                    key={slug}
                                    category={category}
                                    selected={selected}
                                    onPick={(value) => setValue(category, value.id, category.is_multi)}
                                />
                            );
                        })}
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-5">
                        {ANGLE_SLUGS.map((slug) => {
                            const category = categoryBySlug[slug];
                            if (!category) return null;

                            return (
                                <ChipRow
                                    key={slug}
                                    category={category}
                                    selected={selected}
                                    onPick={(value) => setValue(category, value.id, category.is_multi)}
                                />
                            );
                        })}

                        <div>
                            <p className="mb-1.5 text-xs font-medium text-slate-600">Canaux envisagés</p>
                            <div className="flex flex-wrap gap-1.5">
                                {options.channels.map((channel) => {
                                    const active = channels.includes(String(channel.id));
                                    return (
                                        <button
                                            key={channel.id}
                                            type="button"
                                            onClick={() =>
                                                setChannels((current) =>
                                                    active
                                                        ? current.filter((c) => c !== String(channel.id))
                                                        : [...current, String(channel.id)],
                                                )
                                            }
                                            className={`rounded-xl px-3 py-1.5 text-sm ring-1 ring-inset transition ${
                                                active
                                                    ? 'bg-teal-700 text-white ring-teal-700'
                                                    : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50'
                                            }`}
                                        >
                                            {channel.name}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                )}

                {step === 4 && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Outcome label="Qui" items={audience.map((a) => a.label)} icon="👤" />
                            <Outcome label="Problème" items={problems.map((p) => p.label)} icon="💸" />
                            <Outcome
                                label="Déclencheur"
                                items={labelsFor(['trigger']).map((t) => t.label)}
                                icon="⏰"
                            />
                            <Outcome
                                label="Motivation / angle"
                                items={labelsFor(['motivation']).map((m) => m.label)}
                                icon="🎯"
                            />
                            <Outcome label="Produit" items={[product?.name].filter(Boolean)} icon="📦" />
                            <Outcome
                                label="Canaux"
                                items={options.channels
                                    .filter((c) => channels.includes(String(c.id)))
                                    .map((c) => c.name)}
                                icon="📣"
                            />
                        </div>

                        <div className="mt-5 rounded-xl bg-slate-50 p-3">
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Nom proposé</p>
                            <p className="text-sm text-slate-800">{suggestedName || 'Nouvelle idée'}</p>
                            <p className="mt-2 text-[11px] text-slate-500">
                                L&apos;identifiant sera généré automatiquement à l&apos;enregistrement
                                {suggestedReference ? ` (${suggestedReference})` : ''}. Copy, visuel, landing page et UTM se
                                remplissent ensuite sur la fiche.
                            </p>
                        </div>
                    </div>
                )}

                <div className="mt-6 flex items-center justify-between">
                    <Button variant="ghost" onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0}>
                        ← Retour
                    </Button>

                    {step < STEPS.length - 1 ? (
                        <Button onClick={() => setStep((s) => s + 1)} disabled={!canContinue}>
                            Continuer →
                        </Button>
                    ) : (
                        <Button onClick={submit} disabled={saving}>
                            {saving ? 'Création…' : 'Créer l\'idée'}
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

function CardGrid({ children }) {
    return <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">{children}</div>;
}

function PickCard({ icon, label, sublabel, active, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex flex-col items-start gap-2 rounded-2xl border p-4 text-left transition ${
                active
                    ? 'border-teal-600 bg-teal-50 ring-2 ring-teal-600/20'
                    : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-teal-300 hover:shadow-sm'
            }`}
        >
            <span className="text-2xl" aria-hidden>
                {icon}
            </span>
            <span className="text-sm font-medium text-slate-800">{label}</span>
            {sublabel && <span className="text-[11px] text-slate-400">{sublabel}</span>}
        </button>
    );
}

function ChipRow({ category, selected, onPick }) {
    return (
        <div>
            <p className="mb-1.5 text-xs font-medium text-slate-600">
                {category.name}
                {category.is_multi && <span className="ml-1 text-[11px] text-slate-400">(plusieurs possibles)</span>}
            </p>
            <div className="flex flex-wrap gap-1.5">
                {category.values.map((value) => {
                    const active = selected(category, value.id);
                    return (
                        <button
                            key={value.id}
                            type="button"
                            onClick={() => onPick(value)}
                            className={`inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm ring-1 ring-inset transition ${
                                active
                                    ? 'bg-teal-700 text-white ring-teal-700'
                                    : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50'
                            }`}
                        >
                            <span aria-hidden>{iconFor(category.slug, value.code)}</span>
                            {value.label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function Outcome({ label, items, icon }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <span className="mr-1" aria-hidden>
                    {icon}
                </span>
                {label}
            </p>
            <p className="mt-0.5 text-sm text-slate-800">{items.filter(Boolean).join(' · ') || '—'}</p>
        </div>
    );
}
