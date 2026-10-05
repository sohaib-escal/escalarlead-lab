import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Badge, Button, Card, EmptyState, Field, Select, Textarea } from '../../Components/Ui';

export default function AgentConsole({ conversation, models, providers, creatives, openers }) {
    const configured = models.filter((model) => model.configured);
    const [showWorking, setShowWorking] = useState(true);
    const endRef = useRef(null);

    const [images, setImages] = useState([]);
    const fileRef = useRef(null);

    const { data, setData, processing, reset } = useForm({
        body: '',
        ai_model_id: configured[0]?.id ?? models[0]?.id ?? '',
        creative_id: '',
    });

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [conversation?.messages?.length]);

    const send = (text) => {
        if (!text.trim() && images.length === 0) return;

        router.post(
            '/agent',
            { ...data, body: text, images },
            {
                preserveScroll: true,
                forceFormData: images.length > 0,
                onSuccess: () => {
                    reset('body');
                    setImages([]);
                    if (fileRef.current) fileRef.current.value = '';
                },
            },
        );
    };

    if (configured.length === 0) {
        return (
            <>
                <Head title="Tester l'agent" />
                <header className="mb-5">
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">💬 Tester l&apos;agent</h1>
                </header>

                <EmptyState
                    icon="🔑"
                    title="Aucun modèle n'est configuré"
                    description="L'agent ne peut pas répondre sans clé API — et il ne fera jamais semblant. Ajoutez une clé dans le fichier .env à la racine du projet, puis rechargez cette page."
                />

                <Card title="Fournisseurs" className="mt-4">
                    <ul className="space-y-2">
                        {providers.map((provider) => (
                            <li key={provider.key} className="flex items-center gap-2 text-sm">
                                <span aria-hidden>{provider.configured ? '🟢' : '⚪'}</span>
                                <span className="text-slate-800">{provider.label}</span>
                                <code className="ml-auto rounded bg-slate-100 px-2 py-0.5 text-[11px]">
                                    {{ anthropic: 'ANTHROPIC_API_KEY', gemini: 'GEMINI_API_KEY', openai: 'OPENAI_API_KEY' }[
                                        provider.key
                                    ] ?? provider.key}
                                    =…
                                </code>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-3 text-xs text-slate-500">
                        Une seule suffit. Après modification du <code className="rounded bg-slate-100 px-1">.env</code>,
                        exécutez <code className="rounded bg-slate-100 px-1">php artisan config:clear</code>.
                    </p>
                </Card>
            </>
        );
    }

    return (
        <>
            <Head title="Tester l'agent" />

            <header className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">💬 Tester l&apos;agent</h1>
                    <p className="mt-0.5 text-sm text-slate-500">
                        Écrivez comme un propriétaire. L&apos;agent utilise exactement le même chemin qu&apos;en production.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Select className="w-52" value={data.ai_model_id} onChange={(e) => setData('ai_model_id', e.target.value)}>
                        {models.map((model) => (
                            <option key={model.id} value={model.id} disabled={!model.configured}>
                                {model.name}
                                {model.configured ? '' : ' — clé manquante'}
                            </option>
                        ))}
                    </Select>
                    <Button variant="secondary" onClick={() => router.post('/agent/reset', {}, { preserveScroll: true })}>
                        Nouvelle conversation
                    </Button>
                </div>
            </header>

            <div className="grid gap-4 lg:grid-cols-5">
                <section className="lg:col-span-3">
                    <div className="flex h-[34rem] flex-col rounded-2xl border border-slate-200 bg-white">
                        <header className="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
                            <span className="grid size-7 place-items-center rounded-full bg-emerald-100 text-xs">🏠</span>
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-medium text-slate-800">Conversation WhatsApp (simulation)</p>
                                <p className="text-[11px] text-slate-500">
                                    {conversation?.problem_family
                                        ? `Sujet détecté : ${conversation.problem_family}`
                                        : 'Sujet pas encore identifié'}
                                    {conversation?.creative && ` · créa ${conversation.creative}`}
                                </p>
                            </div>
                            {conversation?.status && (
                                <Badge color={conversation.status === 'awaiting_human' ? 'emerald' : 'slate'}>
                                    {conversation.status}
                                </Badge>
                            )}
                        </header>

                        <div className="flex-1 space-y-2 overflow-y-auto bg-[#f3f1ea] px-4 py-3">
                            {!conversation?.messages?.length && (
                                <div className="pt-16 text-center">
                                    <p className="text-sm text-slate-500">Envoyez le premier message.</p>
                                    <div className="mt-3 flex flex-col items-center gap-1.5">
                                        {openers.map((opener) => (
                                            <button
                                                key={opener}
                                                type="button"
                                                onClick={() => send(opener)}
                                                className="max-w-sm rounded-xl bg-white px-3 py-1.5 text-left text-xs text-slate-600 ring-1 ring-slate-200 transition hover:ring-teal-300"
                                            >
                                                {opener}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {conversation?.messages?.map((message) => (
                                <div
                                    key={message.id}
                                    className={`flex ${message.direction === 'inbound' ? 'justify-end' : 'justify-start'}`}
                                >
                                    <div
                                        className={`max-w-[78%] rounded-2xl px-3 py-2 text-sm shadow-xs ${
                                            message.direction === 'inbound'
                                                ? 'rounded-br-sm bg-[#d9fdd3] text-slate-800'
                                                : 'rounded-bl-sm bg-white text-slate-800'
                                        }`}
                                    >
                                        {message.images?.length > 0 && (
                                            <div className="mb-1.5 flex flex-wrap gap-1">
                                                {message.images.map((url) => (
                                                    <a key={url} href={url} target="_blank" rel="noreferrer">
                                                        <img
                                                            src={url}
                                                            alt="photo envoyée"
                                                            className="max-h-40 rounded-lg ring-1 ring-black/5"
                                                        />
                                                    </a>
                                                ))}
                                            </div>
                                        )}
                                        {message.images_ignored && (
                                            <p className="mb-1 text-[11px] text-amber-700">
                                                ⚠️ Ce modèle ne lit pas les photos — elle n&apos;a pas été analysée.
                                            </p>
                                        )}
                                        {message.body && <p className="whitespace-pre-line">{message.body}</p>}
                                        <p className="mt-0.5 text-right text-[10px] text-slate-400">{message.at}</p>
                                    </div>
                                </div>
                            ))}
                            <div ref={endRef} />
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                send(data.body);
                            }}
                            className="border-t border-slate-100 p-3"
                        >
                            {images.length > 0 && (
                                <div className="mb-2 flex flex-wrap gap-1.5">
                                    {images.map((file, index) => (
                                        <span
                                            key={index}
                                            className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-[11px] text-slate-600"
                                        >
                                            🖼️ {file.name}
                                            <button
                                                type="button"
                                                onClick={() => setImages(images.filter((_, i) => i !== index))}
                                                className="text-slate-400 hover:text-rose-600"
                                                aria-label="Retirer"
                                            >
                                                ×
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            )}

                            <div className="flex items-end gap-2">
                                <label
                                    className="cursor-pointer rounded-xl px-2.5 py-2 text-lg ring-1 ring-inset ring-slate-300 transition hover:bg-slate-50"
                                    title="Joindre une photo"
                                >
                                    📎
                                    <input
                                        ref={fileRef}
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        multiple
                                        hidden
                                        onChange={(e) => setImages([...e.target.files].slice(0, 3))}
                                    />
                                </label>
                                <Textarea
                                    rows={2}
                                    value={data.body}
                                    onChange={(e) => setData('body', e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            send(data.body);
                                        }
                                    }}
                                    placeholder="Écrivez, ou envoyez une photo… (Entrée pour envoyer)"
                                />
                                <Button type="submit" disabled={processing || (!data.body.trim() && images.length === 0)}>
                                    {processing ? '…' : 'Envoyer'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </section>

                <aside className="space-y-4 lg:col-span-2">
                    <Card
                        title="Fiche lead en construction"
                        action={
                            <Button size="sm" variant="ghost" onClick={() => setShowWorking((v) => !v)}>
                                {showWorking ? 'Masquer le détail' : 'Voir le détail'}
                            </Button>
                        }
                    >
                        {!conversation?.lead ? (
                            <p className="text-xs text-slate-500">Rien encore — la fiche se remplit au fil de la conversation.</p>
                        ) : (
                            <Lead lead={conversation.lead} showWorking={showWorking} />
                        )}
                    </Card>

                    {showWorking && conversation?.messages?.some((m) => m.meta) && (
                        <Card title="Dernier tour — ce que l'agent a vu" bodyClassName="p-0">
                            <Working
                                meta={[...conversation.messages].reverse().find((m) => m.meta)?.meta}
                            />
                        </Card>
                    )}
                </aside>
            </div>
        </>
    );
}

function Lead({ lead, showWorking }) {
    const rows = [
        ['Nom', lead.full_name],
        ['Code postal', lead.postal_code && `${lead.postal_code}${lead.department ? ` (${lead.department})` : ''}`],
        ['Ville', lead.city],
        ['Téléphone', lead.phone],
        ['Produit', lead.product],
    ].filter(([, value]) => value);

    return (
        <>
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <Badge
                    color={
                        { qualified: 'emerald', appointment_requested: 'emerald', lost: 'rose', not_qualified: 'slate' }[
                            lead.status
                        ] ?? 'amber'
                    }
                >
                    {lead.status_label}
                </Badge>
                {lead.next_target && <span className="text-[11px] text-slate-500">prochaine info : {lead.next_target}</span>}
            </div>

            {rows.length > 0 && (
                <dl className="mb-3 grid grid-cols-[7rem_1fr] gap-x-3 gap-y-1 text-xs">
                    {rows.map(([label, value]) => (
                        <div key={label} className="contents">
                            <dt className="text-slate-500">{label}</dt>
                            <dd className="font-medium text-slate-800">{value}</dd>
                        </div>
                    ))}
                </dl>
            )}

            {lead.parameters.length > 0 && (
                <div className="mb-3 flex flex-wrap gap-1">
                    {lead.parameters.map((p, i) => (
                        <Badge key={i} color="teal">
                            {p.value}
                        </Badge>
                    ))}
                </div>
            )}

            {showWorking && Object.keys(lead.details ?? {}).length > 0 && (
                <dl className="mb-3 grid grid-cols-[7rem_1fr] gap-x-3 gap-y-1 text-xs">
                    {Object.entries(lead.details).map(([key, value]) => (
                        <div key={key} className="contents">
                            <dt className="truncate text-slate-400">{key}</dt>
                            <dd className="text-slate-600">{String(value)}</dd>
                        </div>
                    ))}
                </dl>
            )}

            {lead.missing.length > 0 && (
                <p className="text-[11px] text-amber-700">Manque encore : {lead.missing.join(', ')}</p>
            )}

            {lead.summary && (
                <div className="mt-3 rounded-xl bg-slate-50 p-2.5">
                    <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        Résumé pour le conseiller
                    </p>
                    <pre className="font-sans text-[11px] leading-relaxed whitespace-pre-wrap text-slate-700">
                        {lead.summary}
                    </pre>
                </div>
            )}
        </>
    );
}

function Working({ meta }) {
    if (!meta) return null;

    return (
        <div className="divide-y divide-slate-100 text-xs">
            <div className="px-4 py-2.5">
                <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Modèle</p>
                <p className="font-mono text-[11px] text-slate-700">
                    {meta.provider} · {meta.model}
                </p>
            </div>

            <div className="px-4 py-2.5">
                <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    Connaissances récupérées ({meta.retrieval?.strategy})
                </p>
                {meta.retrieval?.passages?.length ? (
                    <ul className="space-y-0.5">
                        {meta.retrieval.passages.map((p) => (
                            <li key={p.slug} className="flex items-center gap-2">
                                <span className="min-w-0 flex-1 truncate font-mono text-[11px] text-slate-600">{p.slug}</span>
                                <span className="font-mono text-[11px] text-slate-400">{p.score}</span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-[11px] text-slate-400">Aucune — l&apos;agent a simplement posé sa question.</p>
                )}
            </div>

            <div className="px-4 py-2.5">
                <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Extrait de ce message</p>
                {Object.keys(meta.extracted ?? {}).length ? (
                    <ul className="space-y-0.5">
                        {Object.entries(meta.extracted).map(([key, value]) => (
                            <li key={key} className="text-[11px] text-slate-600">
                                <span className="text-slate-400">{key}</span> → {String(value)}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-[11px] text-slate-400">Rien de nouveau.</p>
                )}
            </div>
        </div>
    );
}
