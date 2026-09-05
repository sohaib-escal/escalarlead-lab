import { Head, router, useForm } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import { Badge, Button, Card, Field, Input, Select, Table, Textarea } from '../../Components/Ui';

export default function LandingPagesIndex({ landingPages, types, options }) {
    const [editing, setEditing] = useState(null);

    return (
        <>
            <Head title="Landing pages" />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h1 className="text-lg font-semibold text-slate-900">Landing pages</h1>
                    <p className="text-xs text-slate-500">Une même LP peut servir plusieurs créas.</p>
                </div>
                <Button onClick={() => setEditing(editing === 'new' ? null : 'new')}>{editing === 'new' ? 'Fermer' : '+ Nouvelle LP'}</Button>
            </div>

            {editing === 'new' && (
                <Card title="Nouvelle landing page" className="mb-4">
                    <LandingPageForm types={types} options={options} onDone={() => setEditing(null)} />
                </Card>
            )}

            <Card bodyClassName="p-0">
                <Table head={['Nom', 'URL', 'Type', 'Produit', 'Version', { label: 'Créas', align: 'right' }, '']}>
                    {landingPages.map((page) => (
                        <Fragment key={page.id}>
                            <tr className="hover:bg-slate-50">
                                <td className="px-3 py-2.5 font-medium text-slate-800">
                                    {page.name}
                                    {page.notes && <p className="text-[11px] font-normal text-slate-500">{page.notes}</p>}
                                </td>
                                <td className="px-3 py-2.5">
                                    <a href={page.url} target="_blank" rel="noreferrer" className="text-xs text-teal-700 hover:underline">
                                        {page.url}
                                    </a>
                                </td>
                                <td className="px-3 py-2.5 text-xs">{page.type && <Badge color="slate">{page.type}</Badge>}</td>
                                <td className="px-3 py-2.5 text-xs text-slate-600">{page.product ?? '—'}</td>
                                <td className="px-3 py-2.5 text-xs text-slate-600">{page.version}</td>
                                <td className="px-3 py-2.5 text-right tabular-nums">{page.creatives_count}</td>
                                <td className="px-3 py-2.5 text-right">
                                    <button
                                        type="button"
                                        onClick={() => setEditing(editing === page.id ? null : page.id)}
                                        className="rounded-md px-2 py-1 text-[11px] text-slate-500 ring-1 ring-inset ring-slate-200 hover:bg-slate-100"
                                    >
                                        Éditer
                                    </button>
                                </td>
                            </tr>
                            {editing === page.id && (
                                <tr>
                                    <td colSpan={7} className="bg-slate-50 px-3 py-3">
                                        <LandingPageForm page={page} types={types} options={options} onDone={() => setEditing(null)} />
                                    </td>
                                </tr>
                            )}
                        </Fragment>
                    ))}
                    {landingPages.length === 0 && (
                        <tr>
                            <td colSpan={7} className="px-6 py-10 text-center">
                                <p className="text-2xl" aria-hidden>
                                    ⇱
                                </p>
                                <p className="mt-1 text-sm font-medium text-slate-700">Aucune landing page</p>
                                <p className="mx-auto mt-1 max-w-md text-xs text-slate-500">
                                    C&apos;est la page où atterrit le clic. Une même page peut servir plusieurs créas, et
                                    c&apos;est elle qui donne l&apos;URL de base des UTM.
                                </p>
                            </td>
                        </tr>
                    )}
                </Table>
            </Card>
        </>
    );
}

function LandingPageForm({ page, types, options, onDone }) {
    const isEdit = !!page;
    const { data, setData, post, put, processing, errors } = useForm({
        name: page?.name ?? '',
        url: page?.url ?? '',
        landing_page_type_id: page?.landing_page_type_id ?? '',
        product_id: page?.product_id ?? '',
        version: page?.version ?? 'v1',
        notes: page?.notes ?? '',
        is_active: page?.is_active ?? true,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                isEdit ? put(`/landing-pages/${page.id}`, { onSuccess: onDone }) : post('/landing-pages', { onSuccess: onDone });
            }}
        >
            <div className="grid gap-3 sm:grid-cols-3">
                <Field label="Nom" error={errors.name}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                </Field>
                <Field label="URL" error={errors.url} className="sm:col-span-2">
                    <Input value={data.url} onChange={(e) => setData('url', e.target.value)} placeholder="https://…" />
                </Field>
                <Field label="Type" error={errors.landing_page_type_id}>
                    <Select value={data.landing_page_type_id ?? ''} onChange={(e) => setData('landing_page_type_id', e.target.value)}>
                        <option value="">—</option>
                        {types.map((type) => (
                            <option key={type.id} value={type.id}>
                                {type.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Produit" error={errors.product_id}>
                    <Select value={data.product_id ?? ''} onChange={(e) => setData('product_id', e.target.value)}>
                        <option value="">—</option>
                        {options.products.map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Version" error={errors.version}>
                    <Input value={data.version} onChange={(e) => setData('version', e.target.value)} />
                </Field>
                <Field label="Notes" error={errors.notes} className="sm:col-span-3">
                    <Textarea rows={2} value={data.notes ?? ''} onChange={(e) => setData('notes', e.target.value)} />
                </Field>
            </div>

            <div className="mt-3 flex gap-2">
                <Button type="submit" disabled={processing}>
                    {isEdit ? 'Enregistrer' : 'Ajouter'}
                </Button>
                {isEdit && (
                    <Button
                        type="button"
                        variant="danger"
                        onClick={() => {
                            if (confirm('Supprimer cette landing page ?')) router.delete(`/landing-pages/${page.id}`, { onSuccess: onDone });
                        }}
                    >
                        Supprimer
                    </Button>
                )}
            </div>
        </form>
    );
}
