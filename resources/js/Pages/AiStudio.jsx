import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Button, Card, Field, Input, Select, Textarea, Toggle } from '../Components/Ui';
import { GENERATION_STATUS } from '../lib/icons';
import { dateTime } from '../lib/format';

export default function AiStudio({
    models,
    templates,
    promptProviders,
    generationProviders,
    recentPrompts,
    recentGenerations,
}) {
    const [tab, setTab] = useState('models');

    return (
        <>
            <Head title="AI Studio" />

            <header className="mb-5">
                <h1 className="text-2xl font-semibold tracking-tight text-slate-900">🤖 AI Studio</h1>
                <p className="mt-0.5 text-sm text-slate-500">
                    Le modèle écrit le prompt, vous le validez, le service de génération fabrique le visuel.
                </p>
            </header>

            <div className="mb-5 grid gap-3 lg:grid-cols-2">
                <Card title="Modèles disponibles">
                    <ul className="space-y-2">
                        {promptProviders.map((provider) => (
                            <li key={provider.key} className="flex items-center gap-2">
                                <span aria-hidden>{provider.configured ? '🟢' : '⚪'}</span>
                                <span className="text-sm text-slate-800">{provider.label}</span>
                                <span className="ml-auto text-[11px] text-slate-500">
                                    {provider.configured ? 'clé API configurée' : 'clé API manquante'}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card title="Services de génération">
                    <ul className="space-y-3">
                        {generationProviders.map((provider) => (
                            <li key={provider.key}>
                                <div className="flex items-center gap-2">
                                    <span aria-hidden>{provider.api_generation ? '🟢' : '🟡'}</span>
                                    <span className="text-sm font-medium text-slate-800">{provider.label}</span>
                                    <Badge color={provider.api_generation ? 'emerald' : 'amber'}>
                                        {provider.api_generation ? 'API' : 'transfert manuel'}
                                    </Badge>
                                    {!provider.configured && <Badge color="slate">non configuré</Badge>}
                                </div>
                                <p className="mt-0.5 text-[11px] leading-relaxed text-slate-500">{provider.note}</p>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>

            <nav className="mb-4 flex flex-wrap gap-1.5">
                {[
                    ['models', 'Modèles IA'],
                    ['templates', 'Templates de prompt'],
                    ['activity', 'Activité'],
                ].map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setTab(key)}
                        className={`rounded-xl px-3 py-1.5 text-xs font-medium transition ${
                            tab === key ? 'bg-teal-700 text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </nav>

            {tab === 'models' && <Models models={models} providers={promptProviders} />}
            {tab === 'templates' && <Templates templates={templates} />}
            {tab === 'activity' && <Activity prompts={recentPrompts} generations={recentGenerations} />}
        </>
    );
}

function Models({ models, providers }) {
    const [editing, setEditing] = useState(null);

    return (
        <Card
            title="Modèles IA"
            action={
                <Button size="sm" onClick={() => setEditing(editing === 'new' ? null : 'new')}>
                    {editing === 'new' ? 'Fermer' : '+ Ajouter'}
                </Button>
            }
        >
            {editing === 'new' && (
                <div className="mb-3 rounded-xl bg-slate-50 p-3">
                    <ModelForm providers={providers} onDone={() => setEditing(null)} />
                </div>
            )}

            {models.length === 0 && (
                <p className="rounded-xl bg-slate-50 px-4 py-6 text-center text-xs text-slate-500">
                    Aucun modèle configuré : sans modèle, impossible de générer un prompt. Ajoutez-en un avec son
                    fournisseur et son identifiant (par exemple <code className="rounded bg-white px-1">claude-opus-5</code>).
                </p>
            )}

            <ul className="space-y-2">
                {models.map((model) => (
                    <li key={model.id} className="rounded-xl border border-slate-200 p-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-sm font-medium text-slate-800">{model.name}</span>
                            {model.is_default && <Badge color="teal">par défaut</Badge>}
                            {!model.is_active && <Badge color="slate">inactif</Badge>}
                            <span className="font-mono text-[11px] text-slate-500">{model.model_id}</span>
                            <span className="text-[11px] text-slate-400">
                                {providers.find((p) => p.key === model.provider)?.label ?? model.provider}
                            </span>
                            <span className="ml-auto text-[11px] text-slate-400">{model.prompts_count} prompts</span>
                            <button
                                type="button"
                                onClick={() => setEditing(editing === model.id ? null : model.id)}
                                className="rounded-lg px-2 py-1 text-[11px] text-slate-500 ring-1 ring-inset ring-slate-200 hover:bg-slate-50"
                            >
                                Éditer
                            </button>
                        </div>
                        {model.notes && <p className="mt-1 text-[11px] text-slate-500">{model.notes}</p>}

                        {editing === model.id && (
                            <div className="mt-3 rounded-xl bg-slate-50 p-3">
                                <ModelForm model={model} providers={providers} onDone={() => setEditing(null)} />
                            </div>
                        )}
                    </li>
                ))}
            </ul>
        </Card>
    );
}

function ModelForm({ model, providers, onDone }) {
    const isEdit = !!model;
    const { data, setData, post, put, processing, errors } = useForm({
        name: model?.name ?? '',
        provider: model?.provider ?? providers[0]?.key ?? 'anthropic',
        model_id: model?.model_id ?? '',
        notes: model?.notes ?? '',
        is_default: model?.is_default ?? false,
        is_active: model?.is_active ?? true,
        position: model?.position ?? 0,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                isEdit ? put(`/admin/ai-models/${model.id}`, { onSuccess: onDone }) : post('/admin/ai-models', { onSuccess: onDone });
            }}
        >
            <div className="grid gap-3 sm:grid-cols-4">
                <Field label="Nom" error={errors.name}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Claude Opus 5" />
                </Field>
                <Field label="Fournisseur" error={errors.provider}>
                    <Select value={data.provider} onChange={(e) => setData('provider', e.target.value)}>
                        {providers.map((provider) => (
                            <option key={provider.key} value={provider.key}>
                                {provider.label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Identifiant du modèle" error={errors.model_id}>
                    <Input value={data.model_id} onChange={(e) => setData('model_id', e.target.value)} placeholder="claude-opus-5" />
                </Field>
                <Field label="Ordre">
                    <Input type="number" value={data.position} onChange={(e) => setData('position', Number(e.target.value))} />
                </Field>
                <Field label="Notes" className="sm:col-span-4">
                    <Input value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                </Field>
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-4">
                <Toggle checked={data.is_default} onChange={(v) => setData('is_default', v)} label="Modèle par défaut" />
                <Toggle checked={data.is_active} onChange={(v) => setData('is_active', v)} label="Actif" />
                <Button type="submit" size="sm" disabled={processing}>
                    {isEdit ? 'Enregistrer' : 'Ajouter'}
                </Button>
                {isEdit && (
                    <Button
                        type="button"
                        size="sm"
                        variant="danger"
                        onClick={() => {
                            if (confirm('Supprimer ce modèle ?')) router.delete(`/admin/ai-models/${model.id}`, { onSuccess: onDone });
                        }}
                    >
                        Supprimer
                    </Button>
                )}
            </div>
        </form>
    );
}

function Templates({ templates }) {
    const [editing, setEditing] = useState(null);

    return (
        <Card
            title="Templates de prompt"
            action={
                <Button size="sm" onClick={() => setEditing(editing === 'new' ? null : 'new')}>
                    {editing === 'new' ? 'Fermer' : '+ Ajouter'}
                </Button>
            }
        >
            <p className="mb-3 text-xs text-slate-500">
                Le template décrit comment briefer le modèle. <code className="rounded bg-slate-100 px-1">{'{{brief}}'}</code> est
                remplacé par la fiche de l&apos;idée, ainsi que{' '}
                <code className="rounded bg-slate-100 px-1">{'{{format}}'}</code>,{' '}
                <code className="rounded bg-slate-100 px-1">{'{{channel}}'}</code> et{' '}
                <code className="rounded bg-slate-100 px-1">{'{{desired_response}}'}</code>.
            </p>

            {editing === 'new' && (
                <div className="mb-3 rounded-xl bg-slate-50 p-3">
                    <TemplateForm onDone={() => setEditing(null)} />
                </div>
            )}

            {templates.length === 0 && (
                <p className="rounded-xl bg-slate-50 px-4 py-6 text-center text-xs text-slate-500">
                    Aucun template : le template dit au modèle comment écrire le prompt (ton, garde-fous, format de
                    sortie). Ajoutez-en au moins un pour la vidéo.
                </p>
            )}

            <ul className="space-y-2">
                {templates.map((template) => (
                    <li key={template.id} className="rounded-xl border border-slate-200 p-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-sm font-medium text-slate-800">{template.name}</span>
                            <Badge color="slate">{template.target_format}</Badge>
                            {template.is_default && <Badge color="teal">par défaut</Badge>}
                            <button
                                type="button"
                                onClick={() => setEditing(editing === template.id ? null : template.id)}
                                className="ml-auto rounded-lg px-2 py-1 text-[11px] text-slate-500 ring-1 ring-inset ring-slate-200 hover:bg-slate-50"
                            >
                                Éditer
                            </button>
                        </div>
                        {template.description && <p className="mt-1 text-[11px] text-slate-500">{template.description}</p>}

                        {editing === template.id && (
                            <div className="mt-3 rounded-xl bg-slate-50 p-3">
                                <TemplateForm template={template} onDone={() => setEditing(null)} />
                            </div>
                        )}
                    </li>
                ))}
            </ul>
        </Card>
    );
}

function TemplateForm({ template, onDone }) {
    const isEdit = !!template;
    const { data, setData, post, put, processing, errors } = useForm({
        name: template?.name ?? '',
        target_format: template?.target_format ?? 'video',
        description: template?.description ?? '',
        system_prompt: template?.system_prompt ?? '',
        user_template: template?.user_template ?? 'Creative brief:\n\n{{brief}}\n\nTarget format: {{format}}.\nChannel: {{channel}}.\nDesired viewer response: {{desired_response}}.\n\nWrite the generation prompt.',
        is_default: template?.is_default ?? false,
        is_active: template?.is_active ?? true,
        position: template?.position ?? 0,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                isEdit
                    ? put(`/admin/prompt-templates/${template.id}`, { onSuccess: onDone })
                    : post('/admin/prompt-templates', { onSuccess: onDone });
            }}
        >
            <div className="grid gap-3 sm:grid-cols-3">
                <Field label="Nom" error={errors.name}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                </Field>
                <Field label="Format visé" error={errors.target_format}>
                    <Select value={data.target_format} onChange={(e) => setData('target_format', e.target.value)}>
                        <option value="video">Vidéo</option>
                        <option value="image">Image</option>
                        <option value="any">Tous</option>
                    </Select>
                </Field>
                <Field label="Description">
                    <Input value={data.description ?? ''} onChange={(e) => setData('description', e.target.value)} />
                </Field>
                <Field label="Instructions système" error={errors.system_prompt} className="sm:col-span-3">
                    <Textarea
                        rows={8}
                        className="font-mono text-xs"
                        value={data.system_prompt}
                        onChange={(e) => setData('system_prompt', e.target.value)}
                    />
                </Field>
                <Field label="Message envoyé au modèle" error={errors.user_template} className="sm:col-span-3">
                    <Textarea
                        rows={6}
                        className="font-mono text-xs"
                        value={data.user_template}
                        onChange={(e) => setData('user_template', e.target.value)}
                    />
                </Field>
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-4">
                <Toggle checked={data.is_default} onChange={(v) => setData('is_default', v)} label="Template par défaut" />
                <Toggle checked={data.is_active} onChange={(v) => setData('is_active', v)} label="Actif" />
                <Button type="submit" size="sm" disabled={processing}>
                    {isEdit ? 'Enregistrer' : 'Ajouter'}
                </Button>
            </div>
        </form>
    );
}

function Activity({ prompts, generations }) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Card title="Derniers prompts" bodyClassName="p-0">
                <ul className="divide-y divide-slate-100">
                    {prompts.map((prompt) => (
                        <li key={prompt.id} className="flex items-center gap-2 px-4 py-2.5">
                            {prompt.creative && (
                                <Link href={`/creatives/${prompt.creative.id}`} className="font-mono text-xs text-teal-700 hover:underline">
                                    {prompt.creative.reference}
                                </Link>
                            )}
                            <Badge color={prompt.status === 'validated' ? 'emerald' : 'amber'}>{prompt.status}</Badge>
                            <span className="text-[11px] text-slate-500">
                                v{prompt.version} · {prompt.model}
                            </span>
                            <span className="ml-auto text-[11px] text-slate-400">{dateTime(prompt.created_at)}</span>
                        </li>
                    ))}
                    {prompts.length === 0 && (
                        <li className="px-6 py-8 text-center">
                            <p className="text-sm font-medium text-slate-700">Aucun prompt généré</p>
                            <p className="mx-auto mt-1 max-w-sm text-xs text-slate-500">
                                Les prompts se génèrent depuis une créa, à partir de sa cible. Ouvrez une branche de
                                l&apos;arbre, créez une idée, puis lancez la génération du prompt.
                            </p>
                        </li>
                    )}
                </ul>
            </Card>

            <Card title="Dernières générations" bodyClassName="p-0">
                <ul className="divide-y divide-slate-100">
                    {generations.map((generation) => {
                        const status = GENERATION_STATUS[generation.status] ?? GENERATION_STATUS.queued;
                        return (
                            <li key={generation.id} className="flex items-center gap-2 px-4 py-2.5">
                                {generation.creative && (
                                    <Link
                                        href={`/creatives/${generation.creative.id}`}
                                        className="font-mono text-xs text-teal-700 hover:underline"
                                    >
                                        {generation.creative.reference}
                                    </Link>
                                )}
                                <span className={`rounded-md px-2 py-0.5 text-[11px] font-semibold ${status.tone}`}>
                                    {status.icon} {status.label}
                                </span>
                                <span className="text-[11px] text-slate-500">{generation.provider}</span>
                                <span className="ml-auto text-[11px] text-slate-400">{dateTime(generation.created_at)}</span>
                            </li>
                        );
                    })}
                    {generations.length === 0 && (
                        <li className="px-6 py-8 text-center">
                            <p className="text-sm font-medium text-slate-700">Aucune génération</p>
                            <p className="mx-auto mt-1 max-w-sm text-xs text-slate-500">
                                Une génération part toujours d&apos;un prompt validé. Veo génère via l&apos;API ; Flow se
                                fait à la main et l&apos;asset est rattaché ensuite.
                            </p>
                        </li>
                    )}
                </ul>
            </Card>
        </div>
    );
}
