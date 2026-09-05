import { Head, router, useForm } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import { Badge, Button, Card, Field, Input, Select, Table, Toggle } from '../../Components/Ui';

const TABS = [
    { key: 'parameters', label: 'Paramètres du tree' },
    { key: 'products', label: 'Produits' },
    { key: 'channels', label: 'Canaux' },
    { key: 'statuses', label: 'Statuts' },
    { key: 'ctas', label: 'CTA' },
    { key: 'lp-types', label: 'Types de LP' },
    { key: 'users', label: 'Utilisateurs' },
];

export default function AdminIndex(props) {
    const [tab, setTab] = useState('parameters');

    return (
        <>
            <Head title="Admin" />

            <div className="mb-4">
                <h1 className="text-lg font-semibold text-slate-900">Admin</h1>
                <p className="text-xs text-slate-500">Ajoutez un paramètre, une valeur, un canal ou un statut sans passer par un développeur.</p>
            </div>

            <nav className="mb-4 flex flex-wrap gap-1">
                {TABS.map((item) => (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => setTab(item.key)}
                        className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                            tab === item.key ? 'bg-teal-700 text-white' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50'
                        }`}
                    >
                        {item.label}
                    </button>
                ))}
            </nav>

            {tab === 'parameters' && <Parameters categories={props.categories} groups={props.groups} products={props.products} />}
            {tab === 'products' && <SimpleList resource="products" title="Produits" rows={props.products} fields={productFields} />}
            {tab === 'channels' && <SimpleList resource="channels" title="Canaux" rows={props.channels} fields={channelFields} />}
            {tab === 'statuses' && <SimpleList resource="creative-statuses" title="Statuts de créa" rows={props.statuses} fields={statusFields} />}
            {tab === 'ctas' && <SimpleList resource="cta-options" title="CTA" rows={props.ctas} fields={ctaFields} />}
            {tab === 'lp-types' && <SimpleList resource="landing-page-types" title="Types de landing page" rows={props.landingPageTypes} fields={lpTypeFields} />}
            {tab === 'users' && <Users users={props.users} roles={props.roles} />}
        </>
    );
}

const productFields = [
    { key: 'name', label: 'Nom', type: 'text' },
    { key: 'code', label: 'Code', type: 'text', hint: 'Utilisé dans les IDs de créa' },
    { key: 'color', label: 'Couleur', type: 'text' },
    { key: 'position', label: 'Ordre', type: 'number' },
    { key: 'is_active', label: 'Actif', type: 'bool' },
];

const channelFields = [
    { key: 'name', label: 'Nom', type: 'text' },
    { key: 'code', label: 'Code', type: 'text' },
    { key: 'default_utm_source', label: 'utm_source', type: 'text' },
    { key: 'default_utm_medium', label: 'utm_medium', type: 'text' },
    { key: 'position', label: 'Ordre', type: 'number' },
    { key: 'is_active', label: 'Actif', type: 'bool' },
];

const statusFields = [
    { key: 'name', label: 'Nom', type: 'text' },
    { key: 'color', label: 'Couleur', type: 'text' },
    { key: 'counts_as_live', label: 'Compté comme en ligne', type: 'bool' },
    { key: 'is_archived_state', label: 'État archivé', type: 'bool' },
    { key: 'position', label: 'Ordre', type: 'number' },
    { key: 'is_active', label: 'Actif', type: 'bool' },
];

const ctaFields = [
    { key: 'label', label: 'Libellé', type: 'text' },
    { key: 'position', label: 'Ordre', type: 'number' },
    { key: 'is_active', label: 'Actif', type: 'bool' },
];

const lpTypeFields = [
    { key: 'name', label: 'Nom', type: 'text' },
    { key: 'position', label: 'Ordre', type: 'number' },
    { key: 'is_active', label: 'Actif', type: 'bool' },
];

function defaultsFor(fields, row) {
    return Object.fromEntries(fields.map((field) => [field.key, row?.[field.key] ?? (field.type === 'bool' ? true : field.type === 'number' ? 0 : '')]));
}

function SimpleList({ resource, title, rows, fields }) {
    const [editing, setEditing] = useState(null);

    return (
        <Card title={title} action={<Button size="sm" onClick={() => setEditing(editing === 'new' ? null : 'new')}>{editing === 'new' ? 'Fermer' : '+ Ajouter'}</Button>} bodyClassName="p-0">
            {editing === 'new' && (
                <div className="border-b border-slate-100 bg-slate-50 p-3">
                    <RowForm resource={resource} fields={fields} onDone={() => setEditing(null)} />
                </div>
            )}

            <Table head={[...fields.map((f) => f.label), '']}>
                {rows.map((row) => (
                    <Fragment key={row.id}>
                        <tr className="hover:bg-slate-50">
                            {fields.map((field) => (
                                <td key={field.key} className="px-3 py-2 text-sm text-slate-700">
                                    {field.type === 'bool' ? (row[field.key] ? '✓' : '—') : (row[field.key] ?? '—')}
                                </td>
                            ))}
                            <td className="px-3 py-2 text-right">
                                <button
                                    type="button"
                                    onClick={() => setEditing(editing === row.id ? null : row.id)}
                                    className="rounded-md px-2 py-1 text-[11px] text-slate-500 ring-1 ring-inset ring-slate-200 hover:bg-slate-100"
                                >
                                    Éditer
                                </button>
                            </td>
                        </tr>
                        {editing === row.id && (
                            <tr>
                                <td colSpan={fields.length + 1} className="bg-slate-50 px-3 py-3">
                                    <RowForm resource={resource} fields={fields} row={row} onDone={() => setEditing(null)} />
                                </td>
                            </tr>
                        )}
                    </Fragment>
                ))}
            </Table>
        </Card>
    );
}

function RowForm({ resource, fields, row, onDone, extraDefaults = {} }) {
    const isEdit = !!row;
    const { data, setData, post, put, processing, errors } = useForm({ ...defaultsFor(fields, row), ...extraDefaults });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                isEdit ? put(`/admin/${resource}/${row.id}`, { onSuccess: onDone }) : post(`/admin/${resource}`, { onSuccess: onDone });
            }}
        >
            <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
                {fields.map((field) => (
                    <Field key={field.key} label={field.label} hint={field.hint} error={errors[field.key]}>
                        {field.type === 'bool' ? (
                            <Toggle checked={data[field.key]} onChange={(v) => setData(field.key, v)} label={field.label} />
                        ) : field.type === 'select' ? (
                            <Select value={data[field.key] ?? ''} onChange={(e) => setData(field.key, e.target.value)}>
                                <option value="">—</option>
                                {field.options.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        ) : (
                            <Input
                                type={field.type}
                                value={data[field.key] ?? ''}
                                onChange={(e) => setData(field.key, field.type === 'number' ? Number(e.target.value) : e.target.value)}
                            />
                        )}
                    </Field>
                ))}
            </div>

            <div className="mt-3 flex gap-2">
                <Button type="submit" size="sm" disabled={processing}>
                    {isEdit ? 'Enregistrer' : 'Ajouter'}
                </Button>
                {isEdit && (
                    <Button
                        type="button"
                        size="sm"
                        variant="danger"
                        onClick={() => {
                            if (confirm('Supprimer / archiver cet élément ?')) router.delete(`/admin/${resource}/${row.id}`, { onSuccess: onDone });
                        }}
                    >
                        Supprimer
                    </Button>
                )}
            </div>
        </form>
    );
}

function Parameters({ categories, groups, products }) {
    const [openCategory, setOpenCategory] = useState(categories[0]?.id ?? null);
    const [editing, setEditing] = useState(null);

    const categoryFields = [
        { key: 'name', label: 'Nom', type: 'text' },
        { key: 'group', label: 'Groupe', type: 'select', options: groups.map((g) => ({ value: g, label: g })) },
        { key: 'is_multi', label: 'Choix multiple', type: 'bool' },
        { key: 'in_tree', label: 'Axe du tree', type: 'bool' },
        { key: 'in_naming', label: 'Dans l\'ID de créa', type: 'bool' },
        { key: 'position', label: 'Ordre', type: 'number' },
        { key: 'is_active', label: 'Actif', type: 'bool' },
    ];

    const valueFields = [
        { key: 'label', label: 'Libellé', type: 'text' },
        { key: 'code', label: 'Code', type: 'text', hint: 'Token court utilisé dans l\'ID' },
        { key: 'product_id', label: 'Produit (optionnel)', type: 'select', options: products.map((p) => ({ value: p.id, label: p.name })) },
        { key: 'position', label: 'Ordre', type: 'number' },
        { key: 'is_archived', label: 'Archivé', type: 'bool' },
    ];

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <Card title="Catégories" className="lg:col-span-1" bodyClassName="p-0">
                <ul className="divide-y divide-slate-100">
                    {categories.map((category) => (
                        <li key={category.id}>
                            <button
                                type="button"
                                onClick={() => setOpenCategory(category.id)}
                                className={`flex w-full items-center justify-between gap-2 px-4 py-2 text-left text-sm ${
                                    openCategory === category.id ? 'bg-teal-50 font-medium text-teal-800' : 'text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {category.name}
                                    <span className="ml-1 text-[11px] text-slate-400">{category.group}</span>
                                </span>
                                <span className="flex shrink-0 gap-1">
                                    {category.in_tree && <Badge color="teal">tree</Badge>}
                                    {category.in_naming && <Badge color="amber">ID</Badge>}
                                    <span className="text-[11px] text-slate-400">{category.values.length}</span>
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
                <div className="border-t border-slate-100 p-3">
                    <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Nouvelle catégorie</p>
                    <RowForm resource="parameter-categories" fields={categoryFields} onDone={() => setEditing(null)} />
                </div>
            </Card>

            <div className="space-y-4 lg:col-span-2">
                {categories
                    .filter((category) => category.id === openCategory)
                    .map((category) => (
                        <div key={category.id} className="space-y-4">
                            <Card title={`Réglages — ${category.name}`}>
                                <RowForm resource="parameter-categories" fields={categoryFields} row={category} onDone={() => {}} />
                            </Card>

                            <Card
                                title={`Valeurs (${category.values.length})`}
                                action={
                                    <Button size="sm" onClick={() => setEditing(editing === 'new-value' ? null : 'new-value')}>
                                        {editing === 'new-value' ? 'Fermer' : '+ Ajouter une valeur'}
                                    </Button>
                                }
                                bodyClassName="p-0"
                            >
                                {editing === 'new-value' && (
                                    <div className="border-b border-slate-100 bg-slate-50 p-3">
                                        <RowForm
                                            resource="parameter-values"
                                            fields={valueFields}
                                            extraDefaults={{ parameter_category_id: category.id }}
                                            onDone={() => setEditing(null)}
                                        />
                                    </div>
                                )}

                                {category.values.length === 0 && (
                                    <p className="px-4 py-6 text-center text-xs text-slate-500">
                                        Cette catégorie n&apos;a aucune valeur : elle n&apos;apparaîtra donc ni dans
                                        l&apos;arbre ni dans la création. Ajoutez-en une pour la rendre utilisable.
                                    </p>
                                )}

                                <Table head={['Libellé', 'Code', 'Ordre', 'Archivé', '']}>
                                    {category.values.map((value) => (
                                        <Fragment key={value.id}>
                                            <tr className="hover:bg-slate-50">
                                                <td className="px-3 py-2 text-sm text-slate-800">{value.label}</td>
                                                <td className="px-3 py-2 font-mono text-xs text-slate-600">{value.code}</td>
                                                <td className="px-3 py-2 text-xs text-slate-500">{value.position}</td>
                                                <td className="px-3 py-2 text-xs">{value.is_archived ? '✓' : '—'}</td>
                                                <td className="px-3 py-2 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => setEditing(editing === value.id ? null : value.id)}
                                                        className="rounded-md px-2 py-1 text-[11px] text-slate-500 ring-1 ring-inset ring-slate-200 hover:bg-slate-100"
                                                    >
                                                        Éditer
                                                    </button>
                                                </td>
                                            </tr>
                                            {editing === value.id && (
                                                <tr>
                                                    <td colSpan={5} className="bg-slate-50 px-3 py-3">
                                                        <RowForm
                                                            resource="parameter-values"
                                                            fields={valueFields}
                                                            row={value}
                                                            extraDefaults={{ parameter_category_id: category.id }}
                                                            onDone={() => setEditing(null)}
                                                        />
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    ))}
                                </Table>
                            </Card>
                        </div>
                    ))}
            </div>
        </div>
    );
}

function Users({ users, roles }) {
    const fields = [
        { key: 'name', label: 'Nom', type: 'text' },
        { key: 'email', label: 'Email', type: 'text' },
        { key: 'password', label: 'Mot de passe', type: 'password', hint: 'Laisser vide pour ne pas changer' },
        { key: 'role', label: 'Rôle', type: 'select', options: Object.entries(roles).map(([value, label]) => ({ value, label })) },
        { key: 'is_active', label: 'Actif', type: 'bool' },
    ];

    return <SimpleList resource="users" title="Utilisateurs" rows={users} fields={fields} />;
}
