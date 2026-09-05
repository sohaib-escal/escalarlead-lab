import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge, Button, CopyButton, Field, Input, Select, Textarea } from './Ui';
import { GENERATION_STATUS } from '../lib/icons';
import { dateTime } from '../lib/format';

/**
 * The loop: idea → outcome → AI prompt → review → validate → external generation → asset.
 * Nothing is sent anywhere until the admin validates the prompt.
 */
const STEPS = [
    { key: 'idea', label: 'Idée' },
    { key: 'prompt', label: 'Prompt' },
    { key: 'validated', label: 'Validé' },
    { key: 'generating', label: 'Génération' },
    { key: 'generated', label: 'Asset' },
    { key: 'attached', label: 'Rattaché' },
];

export default function AiPanel({ creative, options }) {
    const prompts = creative.prompts ?? [];
    const latest = prompts[0] ?? null;
    const validated = prompts.find((prompt) => prompt.status === 'validated') ?? null;
    const state = creative.ai_state;

    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <header className="border-b border-slate-100 bg-gradient-to-r from-teal-50 to-white px-4 py-3">
                <div className="flex items-center justify-between gap-2">
                    <h2 className="text-sm font-semibold text-slate-800">🤖 Génération assistée</h2>
                    {state && <Badge color={state.color}>{state.label}</Badge>}
                </div>

                {/* Where we are, in one line, always. */}
                <ol className="mt-2 flex items-center gap-1">
                    {STEPS.map((step, index) => {
                        const done = state ? index < state.step : false;
                        const current = state ? index === state.step : false;

                        return (
                            <li key={step.key} className="flex flex-1 items-center gap-1">
                                <span
                                    className={`h-1.5 flex-1 rounded-full transition ${
                                        done ? 'bg-teal-600' : current ? 'bg-teal-400' : 'bg-slate-200'
                                    }`}
                                />
                                <span
                                    className={`hidden text-[10px] whitespace-nowrap sm:inline ${
                                        current ? 'font-semibold text-teal-800' : 'text-slate-400'
                                    }`}
                                >
                                    {step.label}
                                </span>
                            </li>
                        );
                    })}
                </ol>

                {state && (
                    <p className="mt-2 text-[11px] text-slate-600">
                        {state.help} <span className="font-medium text-teal-800">→ {state.next_action}</span>
                    </p>
                )}
            </header>

            <div className="space-y-4 p-4">
                <OutcomeSummary outcome={creative.outcome} />
                <GenerateForm creative={creative} options={options} hasPrompt={!!latest} />
                {latest && <PromptReview prompt={latest} />}
                {validated && <GenerationLauncher creative={creative} options={options} prompt={validated} />}
                {(creative.generations ?? []).length > 0 && <Generations creative={creative} />}
            </div>
        </section>
    );
}

function OutcomeSummary({ outcome }) {
    if (!outcome) return null;

    const rows = [
        ['QUI', outcome.who, '👤'],
        ['PROBLÈME', outcome.problem, '💸'],
        ['DÉCLENCHEUR', outcome.trigger, '⏰'],
        ['MOTIVATION', outcome.motivation, '🎯'],
        ['OBJECTION', outcome.objection, '🛑'],
        ['PRODUIT', outcome.product ? [outcome.product] : [], '📦'],
        ['CANAL', outcome.channel, '📣'],
        ['RÉPONSE ATTENDUE', outcome.desired_response ? [outcome.desired_response] : [], '✅'],
    ].filter(([, value]) => (value ?? []).length > 0);

    return (
        <div className="rounded-xl bg-slate-50 p-3">
            <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                Ce que l&apos;on cherche à provoquer
            </p>
            <div className="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                {rows.map(([label, values, icon]) => (
                    <div key={label}>
                        <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                            <span className="mr-1" aria-hidden>
                                {icon}
                            </span>
                            {label}
                        </p>
                        <p className="text-sm text-slate-800">{values.join(' · ')}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function GenerateForm({ creative, options, hasPrompt }) {
    const models = options.ai_models ?? [];
    const templates = options.prompt_templates ?? [];

    const { data, setData, post, processing } = useForm({
        ai_model_id: models.find((m) => m.is_default)?.id ?? models[0]?.id ?? '',
        prompt_template_id: templates.find((t) => t.is_default)?.id ?? templates[0]?.id ?? '',
        target_format: 'video',
    });

    // Keep the template consistent with the chosen output format.
    useEffect(() => {
        const match = templates.find((t) => t.target_format === data.target_format);
        if (match) setData('prompt_template_id', match.id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.target_format]);

    if (models.length === 0) {
        return (
            <p className="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                Aucun modèle IA configuré. Ajoutez-en un dans l&apos;AI Studio.
            </p>
        );
    }

    return (
        <div className="grid gap-3 sm:grid-cols-4">
            <Field label="Modèle IA">
                <Select value={data.ai_model_id} onChange={(e) => setData('ai_model_id', e.target.value)}>
                    {models.map((model) => (
                        <option key={model.id} value={model.id}>
                            {model.name}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label="Format visé">
                <Select value={data.target_format} onChange={(e) => setData('target_format', e.target.value)}>
                    <option value="video">Vidéo</option>
                    <option value="image">Image</option>
                </Select>
            </Field>
            <Field label="Template">
                <Select value={data.prompt_template_id} onChange={(e) => setData('prompt_template_id', e.target.value)}>
                    {templates.map((template) => (
                        <option key={template.id} value={template.id}>
                            {template.name}
                        </option>
                    ))}
                </Select>
            </Field>
            <div className="flex items-end">
                <Button
                    className="w-full"
                    disabled={processing}
                    onClick={() => post(`/creatives/${creative.id}/prompts`, { preserveScroll: true })}
                >
                    {processing ? 'Génération…' : hasPrompt ? 'Régénérer' : 'Générer le prompt'}
                </Button>
            </div>
        </div>
    );
}

function PromptReview({ prompt }) {
    const { data, setData, put, processing } = useForm({ body: prompt.body });
    const [dirty, setDirty] = useState(false);

    useEffect(() => {
        setData('body', prompt.body);
        setDirty(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [prompt.id, prompt.body]);

    const isValidated = prompt.status === 'validated';

    return (
        <div className="rounded-xl border border-slate-200">
            <header className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
                <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold text-slate-700">Prompt v{prompt.version}</span>
                    <Badge color={isValidated ? 'emerald' : 'amber'}>{isValidated ? 'validé' : 'à relire'}</Badge>
                    <span className="text-[11px] text-slate-400">
                        {prompt.model} · {prompt.target_format} · {dateTime(prompt.created_at)}
                    </span>
                </div>
                <CopyButton value={data.body} />
            </header>

            <div className="p-3">
                <Textarea
                    rows={12}
                    value={data.body}
                    onChange={(e) => {
                        setData('body', e.target.value);
                        setDirty(true);
                    }}
                    className="font-mono text-xs"
                />

                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        disabled={processing || !dirty}
                        onClick={() => put(`/prompts/${prompt.id}`, { preserveScroll: true, onSuccess: () => setDirty(false) })}
                    >
                        Enregistrer les modifications
                    </Button>

                    {!isValidated && (
                        <Button
                            size="sm"
                            disabled={dirty}
                            onClick={() => router.post(`/prompts/${prompt.id}/validate`, {}, { preserveScroll: true })}
                        >
                            ✓ Valider le prompt
                        </Button>
                    )}

                    {dirty && <span className="text-[11px] text-amber-600">Enregistrez avant de valider.</span>}
                </div>
            </div>
        </div>
    );
}

function GenerationLauncher({ creative, options, prompt }) {
    const providers = options.generation_providers ?? [];
    const [providerKey, setProviderKey] = useState(providers.find((p) => p.api_generation)?.key ?? providers[0]?.key ?? '');
    const provider = providers.find((p) => p.key === providerKey);

    return (
        <div className="rounded-xl border border-teal-200 bg-teal-50/50 p-3">
            <p className="mb-2 text-xs font-semibold text-slate-800">🎬 Générer le visuel</p>

            <div className="grid gap-3 sm:grid-cols-3">
                <Field label="Service de génération" className="sm:col-span-2">
                    <Select value={providerKey} onChange={(e) => setProviderKey(e.target.value)}>
                        {providers.map((item) => (
                            <option key={item.key} value={item.key}>
                                {item.label}
                                {item.api_generation ? '' : ' (transfert manuel)'}
                            </option>
                        ))}
                    </Select>
                </Field>
                <div className="flex items-end">
                    <Button
                        className="w-full"
                        disabled={!provider || !provider.configured}
                        onClick={() =>
                            router.post(
                                `/creatives/${creative.id}/generations`,
                                { creative_prompt_id: prompt.id, provider: providerKey },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {provider?.api_generation ? 'Lancer la génération' : 'Préparer le transfert'}
                    </Button>
                </div>
            </div>

            {provider && (
                <p className={`mt-2 text-[11px] ${provider.api_generation ? 'text-slate-500' : 'text-amber-700'}`}>
                    {provider.api_generation ? 'ℹ️' : '⚠️'} {provider.note}
                    {!provider.configured && ' — non configuré.'}
                </p>
            )}
        </div>
    );
}

function Generations({ creative }) {
    return (
        <div>
            <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Historique des générations</p>
            <ul className="space-y-2">
                {creative.generations.map((generation) => (
                    <Generation key={generation.id} generation={generation} />
                ))}
            </ul>
        </div>
    );
}

function Generation({ generation }) {
    const status = GENERATION_STATUS[generation.status] ?? GENERATION_STATUS.queued;
    const [attaching, setAttaching] = useState(false);
    const { data, setData, post, processing } = useForm({ asset_url: '', asset_reference: '', thumbnail_url: '' });

    return (
        <li className="rounded-xl border border-slate-200 p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold ${status.tone}`}>
                        <span aria-hidden>{status.icon}</span>
                        {status.label}
                    </span>
                    <span className="text-xs text-slate-600">
                        #{generation.id} · {generation.provider}
                        {generation.model ? ` · ${generation.model}` : ''}
                        {generation.prompt_version ? ` · prompt v${generation.prompt_version}` : ''}
                    </span>
                    {generation.is_current && <Badge color="teal">asset actuel</Badge>}
                </div>

                <div className="flex flex-wrap gap-1.5">
                    {['queued', 'generating'].includes(generation.status) && (
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => router.post(`/generations/${generation.id}/refresh`, {}, { preserveScroll: true })}
                        >
                            Actualiser
                        </Button>
                    )}
                    {generation.status === 'awaiting_manual' && (
                        <>
                            {generation.handoff_url && (
                                <Button as="a" href={generation.handoff_url} target="_blank" rel="noreferrer" size="sm" variant="secondary">
                                    Ouvrir Flow ↗
                                </Button>
                            )}
                            <Button size="sm" onClick={() => setAttaching((v) => !v)}>
                                Rattacher le résultat
                            </Button>
                        </>
                    )}
                    {generation.status === 'completed' && !generation.is_current && (
                        <Button size="sm" onClick={() => router.post(`/generations/${generation.id}/use`, {}, { preserveScroll: true })}>
                            Utiliser comme asset
                        </Button>
                    )}
                </div>
            </div>

            {generation.error && <p className="mt-2 text-[11px] text-rose-600">{generation.error}</p>}

            {(generation.local_url || generation.asset_url) && (
                <div className="mt-2 flex flex-wrap items-center gap-2 text-[11px]">
                    {generation.local_url && (
                        <a href={generation.local_url} target="_blank" rel="noreferrer" className="text-teal-700 hover:underline">
                            Copie locale
                        </a>
                    )}
                    {generation.asset_url && (
                        <a href={generation.asset_url} target="_blank" rel="noreferrer" className="truncate text-slate-500 hover:underline">
                            {generation.asset_url}
                        </a>
                    )}
                </div>
            )}

            {attaching && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(`/generations/${generation.id}/attach`, {
                            preserveScroll: true,
                            onSuccess: () => setAttaching(false),
                        });
                    }}
                    className="mt-3 grid gap-2 sm:grid-cols-3"
                >
                    <Field label="URL de l'asset généré" className="sm:col-span-2">
                        <Input value={data.asset_url} onChange={(e) => setData('asset_url', e.target.value)} placeholder="https://…" />
                    </Field>
                    <Field label="Référence (optionnel)">
                        <Input value={data.asset_reference} onChange={(e) => setData('asset_reference', e.target.value)} />
                    </Field>
                    <div className="sm:col-span-3">
                        <Button size="sm" type="submit" disabled={processing}>
                            Enregistrer
                        </Button>
                    </div>
                </form>
            )}
        </li>
    );
}
