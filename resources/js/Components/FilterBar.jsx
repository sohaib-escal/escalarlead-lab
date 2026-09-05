import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button, Select } from './Ui';

/**
 * Global filters. Everything is combinable and lives in the querystring so a
 * filtered view can be shared as a link.
 */
export default function FilterBar({ url, filters, options, extra = null }) {
    const [draft, setDraft] = useState({
        search: filters.search ?? '',
        product: filters.product ?? '',
        channel: filters.channel ?? '',
        campaign: filters.campaign ?? '',
        status: filters.status ?? '',
        rating: filters.rating ?? '',
        landing_page: filters.landing_page ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        params: filters.params ?? {},
    });
    const [open, setOpen] = useState(false);

    const treeCategories = options.categories.filter((category) => category.in_tree);
    const otherCategories = options.categories.filter((category) => !category.in_tree);

    const apply = (next = draft) => {
        const query = { ...next };
        Object.keys(query).forEach((key) => {
            if (query[key] === '' || query[key] === null) delete query[key];
        });

        query.params = Object.fromEntries(
            Object.entries(next.params ?? {}).filter(([, values]) => (values ?? []).filter(Boolean).length > 0),
        );

        if (Object.keys(query.params).length === 0) delete query.params;

        router.get(url, query, { preserveScroll: true, preserveState: false });
    };

    const setParam = (categoryId, valueId) => {
        const params = { ...draft.params };
        if (valueId) params[categoryId] = [valueId];
        else delete params[categoryId];
        const next = { ...draft, params };
        setDraft(next);
        apply(next);
    };

    const set = (key, value) => {
        const next = { ...draft, [key]: value };
        setDraft(next);
        if (key !== 'search') apply(next);
    };

    const activeCount =
        Object.values({ ...draft, params: undefined, search: undefined }).filter(Boolean).length +
        Object.keys(draft.params ?? {}).length;

    return (
        <div className="mb-4 rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
            <div className="flex flex-wrap items-center gap-2">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply();
                    }}
                    className="min-w-56 flex-1"
                >
                    <input
                        value={draft.search}
                        onChange={(e) => set('search', e.target.value)}
                        placeholder="Rechercher…"
                        className="w-full rounded-lg border-0 bg-slate-100 px-3 py-1.5 text-sm placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-inset focus:ring-teal-600"
                    />
                </form>

                <Select className="w-36" value={draft.product} onChange={(e) => set('product', e.target.value)}>
                    <option value="">Produit</option>
                    {options.products.map((product) => (
                        <option key={product.id} value={product.id}>
                            {product.name}
                        </option>
                    ))}
                </Select>

                <Select className="w-36" value={draft.channel} onChange={(e) => set('channel', e.target.value)}>
                    <option value="">Canal</option>
                    {options.channels.map((channel) => (
                        <option key={channel.id} value={channel.id}>
                            {channel.name}
                        </option>
                    ))}
                </Select>

                <Select className="w-40" value={draft.campaign} onChange={(e) => set('campaign', e.target.value)}>
                    <option value="">Campagne</option>
                    {options.campaigns.map((campaign) => (
                        <option key={campaign.id} value={campaign.id}>
                            {campaign.name}
                        </option>
                    ))}
                </Select>

                <Select className="w-32" value={draft.status} onChange={(e) => set('status', e.target.value)}>
                    <option value="">Statut</option>
                    {options.statuses.map((status) => (
                        <option key={status.id} value={status.id}>
                            {status.name}
                        </option>
                    ))}
                </Select>

                <Select className="w-36" value={draft.rating} onChange={(e) => set('rating', e.target.value)}>
                    <option value="">Performance</option>
                    {options.ratings.map((rating) => (
                        <option key={rating.value} value={rating.value}>
                            {rating.label}
                        </option>
                    ))}
                </Select>

                <Button type="button" variant="secondary" size="sm" onClick={() => setOpen((o) => !o)}>
                    Persona {activeCount > 0 && <span className="rounded bg-teal-700 px-1 text-white">{activeCount}</span>}
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        setDraft({ search: '', product: '', channel: '', campaign: '', status: '', rating: '', landing_page: '', date_from: '', date_to: '', params: {} });
                        router.get(url, {}, { preserveState: false });
                    }}
                >
                    Réinitialiser
                </Button>

                {extra}
            </div>

            {open && (
                <div className="mt-3 grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-3 lg:grid-cols-4">
                    {[...treeCategories, ...otherCategories].map((category) => (
                        <label key={category.id} className="block">
                            <span className="mb-0.5 block text-[11px] font-medium text-slate-500">{category.name}</span>
                            <Select
                                value={(draft.params?.[category.id] ?? [''])[0] ?? ''}
                                onChange={(e) => setParam(category.id, e.target.value)}
                            >
                                <option value="">Tous</option>
                                {category.values.map((value) => (
                                    <option key={value.id} value={value.id}>
                                        {value.label}
                                    </option>
                                ))}
                            </Select>
                        </label>
                    ))}

                    <label className="block">
                        <span className="mb-0.5 block text-[11px] font-medium text-slate-500">Créée après le</span>
                        <input
                            type="date"
                            value={draft.date_from}
                            onChange={(e) => set('date_from', e.target.value)}
                            className="w-full rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-teal-600"
                        />
                    </label>
                    <label className="block">
                        <span className="mb-0.5 block text-[11px] font-medium text-slate-500">Créée avant le</span>
                        <input
                            type="date"
                            value={draft.date_to}
                            onChange={(e) => set('date_to', e.target.value)}
                            className="w-full rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-teal-600"
                        />
                    </label>
                </div>
            )}
        </div>
    );
}
