import { Head, router, useForm } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import { Badge, Button, Card, EmptyState, Field, Input, Select, Textarea, Toggle } from '../../Components/Ui';

const FAMILY_ICONS = {
    humidite: '💧',
    infiltration: '🌧️',
    isolation: '🧱',
    chauffage: '🔥',
    facture: '🧾',
    fenetres: '🪟',
    solaire: '☀️',
    general: '💬',
};

const TYPE_COLORS = {
    education: 'teal',
    faq: 'sky',
    objection: 'amber',
    process: 'violet',
    coverage: 'slate',
};

export default function KnowledgeIndex({
    passages,
    guardrails,
    guardrailBlock,
    families,
    contentTypes,
    kinds,
    products,
    filters,
    preview,
    budget,
}) {
    const [tab, setTab] = useState('passages');
    const [editing, setEditing] = useState(null);

    return (
        <>
            <Head title="Connaissances" />

            <header className="mb-5">
                <h1 className="text-2xl font-semibold tracking-tight text-slate-900">📚 Connaissances de l&apos;agent</h1>
                <p className="mt-0.5 text-sm text-slate-500">
                    Ce que l&apos;agent WhatsApp peut expliquer, et ce qu&apos;il ne doit jamais affirmer.
                </p>
            </header>

            <nav className="mb-4 flex flex-wrap gap-1.5">
                {[
                    ['passages', `Passages (${passages.length})`],
                    ['guardrails', `Règles permanentes (${guardrails.length})`],
                    ['preview', 'Tester la recherche'],
                ].map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setTab(key)}
                        className={`rounded-xl px-3 py-1.5 text-xs font-medium transition ${
                            tab === key
                                ? 'bg-teal-700 text-white'
                                : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </nav>

            {tab === 'passages' && (
                <Passages
                    passages={passages}
                    families={families}
                    contentTypes={contentTypes}
                    products={products}
                    filters={filters}
                    editing={editing}
                    setEditing={setEditing}
                />
            )}

            {tab === 'guardrails' && (
                <Guardrails guardrails={guardrails} kinds={kinds} block={guardrailBlock} editing={editing} setEditing={setEditing} />
            )}

            {tab === 'preview' && <Preview preview={preview} families={families} budget={budget} />}
        </>
    );
}

function Passages({ passages, families, contentTypes, products, filters, editing, setEditing }) {
    const grouped = passages.reduce((acc, passage) => {
        const key = passage.problem_family ?? 'general';
        (acc[key] ??= []).push(passage);
        return acc;
    }, {});

    return (
        <>
            <Card className="mb-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        className="w-56"
                        value={filters.family ?? ''}
                        onChange={(e) => router.get('/knowledge', { ...filters, family: e.target.value || undefined })}
                    >
                        <option value="">Tous les sujets</option>
                        {Object.entries(families).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </Select>
                    <Select
                        className="w-44"
                        value={filters.content_type ?? ''}
                        onChange={(e) => router.get('/knowledge', { ...filters, content_type: e.target.value || undefined })}
                    >
                        <option value="">Tous les types</option>
                        {Object.entries(contentTypes).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </Select>
                    <Button variant="ghost" size="sm" onClick={() => router.get('/knowledge')}>
                        Réinitialiser
                    </Button>
                    <Button size="sm" className="ml-auto" onClick={() => setEditing(editing === 'new' ? null : 'new')}>
                        {editing === 'new' ? 'Fermer' : '+ Ajouter un passage'}
                    </Button>
                </div>

                {editing === 'new' && (
                    <div className="mt-3 rounded-xl bg-slate-50 p-3">
                        <PassageForm
                            families={families}
                            contentTypes={contentTypes}
                            products={products}
                            onDone={() => setEditing(null)}
                        />
                    </div>
                )}
            </Card>

            {passages.length === 0 ? (
                <EmptyState
                    icon="📚"
                    title="Aucun passage de connaissance"
                    description="C'est ce que l'agent peut expliquer à un propriétaire : causes possibles d'un problème, réponses aux questions fréquentes, objections. Sans passages, l'agent sait poser des questions mais ne sait rien expliquer."
                    action={
                        <Button size="sm" onClick={() => setEditing('new')}>
                            Ajouter le premier passage
                        </Button>
                    }
                />
            ) : (
                <div className="space-y-5">
                    {Object.entries(grouped).map(([family, rows]) => (
                        <section key={family}>
                            <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-800">
                                <span aria-hidden>{FAMILY_ICONS[family] ?? '•'}</span>
                                {families[family] ?? family}
                                <span className="text-[11px] font-normal text-slate-400">{rows.length}</span>
                            </h2>

                            <div className="space-y-2">
                                {rows.map((passage) => (
                                    <Fragment key={passage.id}>
                                        <article
                                            className={`rounded-xl border bg-white p-3 ${
                                                passage.is_active ? 'border-slate-200' : 'border-dashed border-slate-300 opacity-60'
                                            }`}
                                        >
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="text-sm font-medium text-slate-800">{passage.title}</span>
                                                <Badge color={TYPE_COLORS[passage.content_type] ?? 'slate'}>
                                                    {passage.content_type}
                                                </Badge>
                                                {passage.product && <Badge color="teal">{passage.product}</Badge>}
                                                {!passage.is_active && <Badge color="slate">inactif</Badge>}
                                                <span className="ml-auto text-[11px] tabular-nums text-slate-400">
                                                    ~{passage.tokens} tokens
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => setEditing(editing === passage.id ? null : passage.id)}
                                                    className="rounded-lg px-2 py-1 text-[11px] text-slate-500 ring-1 ring-inset ring-slate-200 hover:bg-slate-50"
                                                >
                                                    Éditer
                                                </button>
                                            </div>

                                            <p className="mt-1.5 line-clamp-3 text-xs leading-relaxed text-slate-600">{passage.body}</p>

                                            {passage.never_claim && (
                                                <p className="mt-1.5 text-[11px] text-rose-700">⛔ {passage.never_claim}</p>
                                            )}

                                            {passage.keywords && (
                                                <p className="mt-1 truncate text-[11px] text-slate-400">🔍 {passage.keywords}</p>
                                            )}
                                        </article>

                                        {editing === passage.id && (
                                            <div className="rounded-xl bg-slate-50 p-3">
                                                <PassageForm
                                                    passage={passage}
                                                    families={families}
                                                    contentTypes={contentTypes}
                                                    products={products}
                                                    onDone={() => setEditing(null)}
                                                />
                                            </div>
                                        )}
                                    </Fragment>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            )}
        </>
    );
}

function PassageForm({ passage, families, contentTypes, products, onDone }) {
    const isEdit = !!passage;
    const { data, setData, post, put, processing, errors } = useForm({
        title: passage?.title ?? '',
        body: passage?.body ?? '',
        keywords: passage?.keywords ?? '',
        problem_family: passage?.problem_family ?? 'general',
        content_type: passage?.content_type ?? 'education',
        product_id: passage?.product_id ?? '',
        never_claim: passage?.never_claim ?? '',
        source: passage?.source ?? '',
        position: passage?.position ?? 0,
        is_active: passage?.is_active ?? true,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                isEdit
                    ? put(`/admin/knowledge-passages/${passage.id}`, { onSuccess: onDone })
                    : post('/admin/knowledge-passages', { onSuccess: onDone });
            }}
        >
            <div className="grid gap-3 sm:grid-cols-4">
                <Field label="Titre" error={errors.title} className="sm:col-span-2" hint="Un intitulé de sujet, pas une phrase de client.">
                    <Input value={data.title} onChange={(e) => setData('title', e.target.value)} />
                </Field>
                <Field label="Sujet" error={errors.problem_family}>
                    <Select value={data.problem_family} onChange={(e) => setData('problem_family', e.target.value)}>
                        {Object.entries(families).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Type" error={errors.content_type}>
                    <Select value={data.content_type} onChange={(e) => setData('content_type', e.target.value)}>
                        {Object.entries(contentTypes).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </Select>
                </Field>

                <Field
                    label="Contenu"
                    error={errors.body}
                    className="sm:col-span-4"
                    hint="Court, en français simple. L'agent le reformule, il ne le récite pas."
                >
                    <Textarea rows={5} value={data.body} onChange={(e) => setData('body', e.target.value)} />
                </Field>

                <Field
                    label="Mots-clés"
                    error={errors.keywords}
                    className="sm:col-span-4"
                    hint="Les mots que le propriétaire tape vraiment : « buée », « taches noires », « ça sent le renfermé ». Ce champ pèse lourd dans la recherche."
                >
                    <Input value={data.keywords ?? ''} onChange={(e) => setData('keywords', e.target.value)} />
                </Field>

                <Field label="À ne jamais affirmer" error={errors.never_claim} className="sm:col-span-2">
                    <Input value={data.never_claim ?? ''} onChange={(e) => setData('never_claim', e.target.value)} />
                </Field>
                <Field label="Produit concerné" error={errors.product_id}>
                    <Select value={data.product_id ?? ''} onChange={(e) => setData('product_id', e.target.value)}>
                        <option value="">Tous</option>
                        {products.map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Ordre" error={errors.position}>
                    <Input type="number" value={data.position} onChange={(e) => setData('position', Number(e.target.value))} />
                </Field>
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-4">
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
                            if (confirm('Supprimer ce passage ?')) {
                                router.delete(`/admin/knowledge-passages/${passage.id}`, { onSuccess: onDone });
                            }
                        }}
                    >
                        Supprimer
                    </Button>
                )}
            </div>
        </form>
    );
}

function Guardrails({ guardrails, kinds, block, editing, setEditing }) {
    const grouped = guardrails.reduce((acc, rule) => {
        (acc[rule.kind] ??= []).push(rule);
        return acc;
    }, {});

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <div className="space-y-4 lg:col-span-2">
                <Card
                    title="Règles présentes à chaque message"
                    action={
                        <Button size="sm" onClick={() => setEditing(editing === 'new-rule' ? null : 'new-rule')}>
                            {editing === 'new-rule' ? 'Fermer' : '+ Ajouter'}
                        </Button>
                    }
                >
                    <p className="mb-3 text-xs text-slate-500">
                        Ces règles ne sont jamais « recherchées » : elles sont injectées dans chaque message envoyé au modèle.
                        Une règle de conformité qui ne serait retrouvée qu&apos;une fois sur deux ne servirait à rien.
                    </p>

                    {editing === 'new-rule' && (
                        <div className="mb-3 rounded-xl bg-slate-50 p-3">
                            <GuardrailForm kinds={kinds} onDone={() => setEditing(null)} />
                        </div>
                    )}

                    {guardrails.length === 0 ? (
                        <EmptyState
                            icon="🛡️"
                            title="Aucune règle permanente"
                            description="C'est ici que vivent le ton, la persona et surtout ce que l'agent ne doit jamais affirmer : aides garanties, économies chiffrées, diagnostic à distance. Sans ces règles, rien ne contraint le modèle."
                        />
                    ) : (
                        Object.entries(grouped).map(([kind, rules]) => (
                            <div key={kind} className="mb-4 last:mb-0">
                                <p className="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    {kinds[kind] ?? kind}
                                </p>
                                <ul className="space-y-1.5">
                                    {rules.map((rule) => (
                                        <Fragment key={rule.id}>
                                            <li
                                                className={`rounded-xl border p-2.5 ${
                                                    rule.is_active ? 'border-slate-200 bg-white' : 'border-dashed border-slate-300 opacity-60'
                                                }`}
                                            >
                                                <div className="flex items-start gap-2">
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-xs font-medium text-slate-800">{rule.name}</p>
                                                        <p className="mt-0.5 text-xs leading-relaxed text-slate-600">{rule.body}</p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => setEditing(editing === rule.id ? null : rule.id)}
                                                        className="shrink-0 rounded-lg px-2 py-1 text-[11px] text-slate-500 ring-1 ring-inset ring-slate-200 hover:bg-slate-50"
                                                    >
                                                        Éditer
                                                    </button>
                                                </div>
                                            </li>
                                            {editing === rule.id && (
                                                <li className="rounded-xl bg-slate-50 p-3">
                                                    <GuardrailForm rule={rule} kinds={kinds} onDone={() => setEditing(null)} />
                                                </li>
                                            )}
                                        </Fragment>
                                    ))}
                                </ul>
                            </div>
                        ))
                    )}
                </Card>
            </div>

            <Card title="Bloc envoyé au modèle" bodyClassName="p-0">
                <pre className="max-h-[32rem] overflow-auto px-4 py-3 font-mono text-[11px] leading-relaxed whitespace-pre-wrap text-slate-700">
                    {block || 'Aucune règle active.'}
                </pre>
            </Card>
        </div>
    );
}

function GuardrailForm({ rule, kinds, onDone }) {
    const isEdit = !!rule;
    const { data, setData, post, put, processing, errors } = useForm({
        name: rule?.name ?? '',
        kind: rule?.kind ?? 'rule',
        body: rule?.body ?? '',
        position: rule?.position ?? 0,
        is_active: rule?.is_active ?? true,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                isEdit
                    ? put(`/admin/agent-guardrails/${rule.id}`, { onSuccess: onDone })
                    : post('/admin/agent-guardrails', { onSuccess: onDone });
            }}
        >
            <div className="grid gap-3 sm:grid-cols-3">
                <Field label="Nom" error={errors.name}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                </Field>
                <Field label="Catégorie" error={errors.kind}>
                    <Select value={data.kind} onChange={(e) => setData('kind', e.target.value)}>
                        {Object.entries(kinds).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Ordre">
                    <Input type="number" value={data.position} onChange={(e) => setData('position', Number(e.target.value))} />
                </Field>
                <Field label="Règle" error={errors.body} className="sm:col-span-3">
                    <Textarea rows={3} value={data.body} onChange={(e) => setData('body', e.target.value)} />
                </Field>
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-4">
                <Toggle checked={data.is_active} onChange={(v) => setData('is_active', v)} label="Active" />
                <Button type="submit" size="sm" disabled={processing}>
                    {isEdit ? 'Enregistrer' : 'Ajouter'}
                </Button>
            </div>
        </form>
    );
}

function Preview({ preview, families, budget }) {
    const { data, setData, get, processing } = useForm({
        preview: preview?.text ?? '',
        preview_family: preview?.family ?? '',
    });

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Card title="Tester une phrase de propriétaire">
                <p className="mb-3 text-xs text-slate-500">
                    Écrivez ce qu&apos;un propriétaire pourrait envoyer. Vous verrez exactement ce que l&apos;agent recevrait
                    comme connaissances — et donc pourquoi il répondrait ce qu&apos;il répondrait.
                </p>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        get('/knowledge', { preserveState: true });
                    }}
                >
                    <Field label="Message">
                        <Textarea
                            rows={3}
                            value={data.preview}
                            onChange={(e) => setData('preview', e.target.value)}
                            placeholder="j'ai de la buée sur les vitres et des taches noires"
                        />
                    </Field>
                    <Field label="Sujet connu de la conversation" className="mt-3" hint="Ce que l'agent sait déjà du problème.">
                        <Select value={data.preview_family} onChange={(e) => setData('preview_family', e.target.value)}>
                            <option value="">Inconnu</option>
                            {Object.entries(families).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Button type="submit" className="mt-3" disabled={processing || !data.preview}>
                        Tester
                    </Button>
                </form>

                <p className="mt-4 text-[11px] text-slate-400">
                    Budget : {budget.max_passages} passages maximum, ~{budget.max_tokens} tokens.
                </p>
            </Card>

            <Card title="Ce que l'agent recevrait" bodyClassName="p-0">
                {!preview ? (
                    <p className="px-4 py-10 text-center text-xs text-slate-500">
                        Lancez un test pour voir les passages retenus.
                    </p>
                ) : (
                    <div>
                        <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-2.5">
                            <Badge color={preview.strategy === 'none' ? 'rose' : 'teal'}>{preview.strategy}</Badge>
                            <span className="text-[11px] text-slate-500">
                                {preview.passages.length} passage(s) · {preview.tokens} tokens
                            </span>
                        </div>

                        {preview.passages.length === 0 ? (
                            <p className="px-4 py-8 text-center text-xs text-slate-500">
                                Aucune connaissance retenue. L&apos;agent poserait simplement sa question suivante — ce qui est
                                souvent le bon comportement pour « bonjour ».
                            </p>
                        ) : (
                            <>
                                <ul className="divide-y divide-slate-100">
                                    {preview.passages.map((hit, index) => (
                                        <li key={hit.slug} className="flex items-center gap-2 px-4 py-2">
                                            <span className="font-mono text-[11px] text-slate-400">{index + 1}</span>
                                            <span className="min-w-0 flex-1 truncate text-xs text-slate-800">
                                                {preview.titles[index]}
                                            </span>
                                            <span className="font-mono text-[11px] text-slate-400">{hit.matched_by}</span>
                                            <span className="font-mono text-[11px] tabular-nums text-slate-500">{hit.score}</span>
                                        </li>
                                    ))}
                                </ul>
                                <pre className="max-h-80 overflow-auto border-t border-slate-100 px-4 py-3 font-mono text-[11px] leading-relaxed whitespace-pre-wrap text-slate-600">
                                    {preview.prompt_block}
                                </pre>
                            </>
                        )}
                    </div>
                )}
            </Card>
        </div>
    );
}
