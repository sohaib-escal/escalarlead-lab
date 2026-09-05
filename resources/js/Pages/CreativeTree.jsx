import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import CreativeCard from '../Components/CreativeCard';
import VariationModal from '../Components/VariationModal';
import { Button, Progress, Select } from '../Components/Ui';
import { coverageOf, iconFor } from '../lib/icons';
import { euro, num } from '../lib/format';
import { createUrlFor } from '../lib/branch';

/**
 * The tree as a set of columns you walk left to right, one decision per column.
 * Everything you need to judge a branch — how much it was tested, whether it
 * wins, what it costs — sits on the card itself.
 */
export default function CreativeTree({
    axes,
    availableAxes,
    tree,
    missingRoots,
    gaps,
    totals,
    options,
    selection,
    branchCreatives,
}) {
    const [variationFor, setVariationFor] = useState(null);
    const [editAxes, setEditAxes] = useState(false);

    const categoriesBySlug = useMemo(
        () => Object.fromEntries(options.categories.map((category) => [category.slug, category.id])),
        [options.categories],
    );

    const reload = (nextAxes, nextSelection = selection) =>
        router.get('/creative-tree', { axes: nextAxes, selection: nextSelection }, { preserveState: false });

    // Walk the tree along the current selection to build one column per level.
    const { columns, trail } = useMemo(() => {
        const columns = [];
        const trail = [];
        let nodes = tree;
        let missing = missingRoots ?? [];

        for (let level = 0; level <= selection.length; level += 1) {
            columns.push({ level, nodes, missing, axis: axes[level] });

            const step = selection[level];
            if (!step) break;

            const [axis, valueId] = step.split(':');
            const node = nodes.find((n) => n.axis === axis && String(n.value_id) === String(valueId));

            if (!node) break;

            trail.push(node);
            nodes = node.children;
            missing = node.missing_children;
        }

        return { columns, trail };
    }, [tree, missingRoots, selection, axes]);

    const active = trail[trail.length - 1] ?? null;

    const pick = (node) => {
        const level = node.path.length - 1;
        reload(axes, [...selection.slice(0, level), `${node.axis}:${node.value_id}`]);
    };

    const coverage = useMemo(() => {
        let tested = 0;
        let missing = 0;
        const walk = (nodes) =>
            nodes.forEach((node) => {
                if (node.is_leaf) tested += 1;
                missing += node.missing_children.length;
                walk(node.children);
            });
        walk(tree);
        return { tested, total: tested + missing };
    }, [tree]);

    const coveragePct = coverage.total > 0 ? Math.round((coverage.tested / coverage.total) * 100) : 0;
    const usedAxes = new Set(axes);

    // Keyboard: ←/→ to move between levels, ↑/↓ between siblings, C to create here.
    useEffect(() => {
        const handler = (event) => {
            if (event.metaKey || event.ctrlKey || event.altKey) return;
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) return;

            const level = selection.length - 1;
            const column = columns[Math.max(0, level)];
            const siblings = column?.nodes ?? [];
            const currentIndex = siblings.findIndex((node) => `${node.axis}:${node.value_id}` === selection[level]);

            const move = (delta) => {
                if (siblings.length === 0) return;
                const next = siblings[(currentIndex + delta + siblings.length) % siblings.length];
                if (next) {
                    event.preventDefault();
                    pick(next);
                }
            };

            switch (event.key) {
                case 'ArrowDown':
                    return move(1);
                case 'ArrowUp':
                    return move(-1);
                case 'ArrowRight':
                case 'Enter': {
                    const child = (active?.children ?? columns[columns.length - 1]?.nodes ?? [])[0];
                    if (child) {
                        event.preventDefault();
                        pick(child);
                    }
                    return;
                }
                case 'ArrowLeft':
                    if (selection.length > 0) {
                        event.preventDefault();
                        reload(axes, selection.slice(0, -1));
                    }
                    return;
                case 'c':
                case 'C':
                    if (active) {
                        event.preventDefault();
                        router.visit(createUrlFor(trail.map((step) => ({ axis: step.axis, value_id: step.value_id })), categoriesBySlug));
                    }
                    return;
                default:
            }
        };

        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    });

    return (
        <>
            <Head title="Creative Tree" />

            <header className="mb-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">🌳 Creative Tree</h1>
                    <p className="mt-0.5 text-sm text-slate-500">
                        Descendez branche par branche. Ce qui est gris n&apos;a jamais été testé.
                    </p>
                    <p className="mt-1 text-[11px] text-slate-400">
                        Clavier : <Key>↑</Key> <Key>↓</Key> pour changer de branche, <Key>→</Key> pour descendre,{' '}
                        <Key>←</Key> pour remonter, <Key>C</Key> pour créer ici.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Stat icon="🧪" value={num(totals.creatives)} label="créas" />
                    <Stat icon="🟢" value={num(totals.live)} label="en ligne" />
                    <Stat icon="🏆" value={num(totals.winners)} label="winners" />
                    <div className="w-44 rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                        <div className="flex items-baseline justify-between">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Couverture</span>
                            <span className="text-sm font-semibold tabular-nums text-slate-900">{coveragePct} %</span>
                        </div>
                        <Progress value={coverage.tested} max={coverage.total || 1} className="mt-1" />
                        <p className="mt-1 text-[10px] text-slate-500">
                            {coverage.tested} testées · {coverage.total - coverage.tested} à tester
                        </p>
                    </div>
                </div>
            </header>

            {/* The path you are standing on. */}
            <div className="mb-3 flex flex-wrap items-center gap-1.5">
                <button
                    type="button"
                    onClick={() => reload(axes, [])}
                    className={`rounded-full px-3 py-1 text-xs transition ${
                        selection.length === 0 ? 'bg-teal-700 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                    }`}
                >
                    Tout
                </button>
                {trail.map((node, index) => (
                    <span key={node.key} className="flex items-center gap-1.5">
                        <span className="text-slate-300">→</span>
                        <button
                            type="button"
                            onClick={() => reload(axes, selection.slice(0, index + 1))}
                            className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs transition ${
                                index === trail.length - 1
                                    ? 'bg-teal-700 text-white'
                                    : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                            }`}
                        >
                            <span aria-hidden>{iconFor(node.axis, node.code)}</span>
                            {node.label}
                        </button>
                    </span>
                ))}

                <button
                    type="button"
                    onClick={() => setEditAxes((v) => !v)}
                    className="ml-auto rounded-full px-2.5 py-1 text-[11px] text-slate-500 ring-1 ring-slate-200 hover:bg-white"
                >
                    ⚙ Axes
                </button>
            </div>

            {editAxes && (
                <div className="mb-3 flex flex-wrap items-center gap-2 rounded-xl bg-white p-3 ring-1 ring-slate-200">
                    {axes.map((axis, index) => {
                        const definition = availableAxes.find((a) => a.key === axis);
                        return (
                            <span
                                key={axis}
                                className="inline-flex items-center gap-1 rounded-full bg-slate-100 py-1 pr-1.5 pl-2.5 text-xs text-slate-700"
                            >
                                <span className="text-slate-400">{index + 1}.</span>
                                {definition?.label ?? axis}
                                {index > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            const next = [...axes];
                                            [next[index - 1], next[index]] = [next[index], next[index - 1]];
                                            reload(next, []);
                                        }}
                                        className="rounded-full px-1 text-slate-400 hover:bg-slate-200"
                                        aria-label="Monter"
                                    >
                                        ↑
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => reload(axes.filter((a) => a !== axis), [])}
                                    className="rounded-full px-1 text-slate-400 hover:bg-slate-200"
                                    aria-label="Retirer"
                                >
                                    ×
                                </button>
                            </span>
                        );
                    })}

                    <Select className="w-44" value="" onChange={(e) => e.target.value && reload([...axes, e.target.value], [])}>
                        <option value="">+ Ajouter un axe…</option>
                        {availableAxes
                            .filter((axis) => !usedAxes.has(axis.key))
                            .map((axis) => (
                                <option key={axis.key} value={axis.key}>
                                    {axis.label}
                                </option>
                            ))}
                    </Select>

                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => reload(['product', 'specific-problem', 'gender', 'age', 'motivation', 'channel'], [])}
                    >
                        Réinitialiser
                    </Button>
                </div>
            )}

            {/* The columns. */}
            <div className="mb-4 flex snap-x gap-3 overflow-x-auto pb-2">
                {columns.map((column) => (
                    <Column
                        key={column.level}
                        column={column}
                        availableAxes={availableAxes}
                        selection={selection}
                        onPick={pick}
                        categoriesBySlug={categoriesBySlug}
                    />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="lg:col-span-2">
                    <BranchPanel
                        node={active}
                        trail={trail}
                        creatives={branchCreatives}
                        categoriesBySlug={categoriesBySlug}
                        onVariation={setVariationFor}
                    />
                </section>

                <OpportunityPanel gaps={gaps} categoriesBySlug={categoriesBySlug} />
            </div>

            <VariationModal
                creative={variationFor}
                categories={options.categories}
                open={!!variationFor}
                onClose={() => setVariationFor(null)}
            />
        </>
    );
}

function Column({ column, availableAxes, selection, onPick, categoriesBySlug }) {
    const axisKey = column.axis;
    const label = availableAxes.find((a) => a.key === axisKey)?.label ?? axisKey;
    const selectedKey = selection[column.level];

    if (!axisKey) return null;

    return (
        <div className="w-64 shrink-0 snap-start rounded-2xl border border-slate-200 bg-white">
            <header className="flex items-center gap-1.5 border-b border-slate-100 px-3 py-2">
                <span aria-hidden>{iconFor(axisKey)}</span>
                <h2 className="text-xs font-semibold uppercase tracking-wide text-slate-600">{label}</h2>
                <span className="ml-auto text-[11px] text-slate-400">{column.nodes.length}</span>
            </header>

            <ul className="max-h-[26rem] space-y-1 overflow-y-auto p-2">
                {column.nodes.map((node) => {
                    const status = coverageOf(node);
                    const isSelected = selectedKey === `${node.axis}:${node.value_id}`;

                    return (
                        <li key={node.key}>
                            <button
                                type="button"
                                onClick={() => onPick(node)}
                                className={`w-full rounded-xl px-2.5 py-2 text-left transition ${
                                    isSelected ? 'bg-teal-700 text-white' : 'hover:bg-slate-50'
                                }`}
                            >
                                <span className="flex items-center gap-2">
                                    <span className="text-base" aria-hidden>
                                        {iconFor(node.axis, node.code)}
                                    </span>
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium">{node.label}</span>
                                    <span aria-hidden title={status.label}>
                                        {status.icon}
                                    </span>
                                </span>

                                <span
                                    className={`mt-1 flex items-center gap-2 text-[11px] tabular-nums ${
                                        isSelected ? 'text-teal-100' : 'text-slate-500'
                                    }`}
                                >
                                    <span>{node.creatives} créas</span>
                                    {node.live > 0 && <span>· {node.live} live</span>}
                                    {node.winners > 0 && <span>· 🏆 {node.winners}</span>}
                                </span>

                                {node.cost_per_qualified !== null && (
                                    <span className={`text-[11px] tabular-nums ${isSelected ? 'text-teal-100' : 'text-slate-400'}`}>
                                        {euro(node.cost_per_qualified)} / lead qualifié
                                    </span>
                                )}

                                {(node.children.length > 0 || node.missing_children.length > 0) && (
                                    <span className="mt-1.5 block">
                                        <Progress
                                            value={node.children.length}
                                            max={node.children.length + node.missing_children.length}
                                            tone={isSelected ? 'bg-white/70' : 'bg-emerald-500'}
                                        />
                                    </span>
                                )}
                            </button>
                        </li>
                    );
                })}

                {column.missing.map((missing) => (
                    <li key={`missing-${missing.value_id}`}>
                        <div className="group flex items-center gap-2 rounded-xl px-2.5 py-2 transition hover:bg-amber-50">
                            <span className="text-base opacity-30" aria-hidden>
                                {iconFor(missing.axis, missing.code)}
                            </span>
                            <span className="min-w-0 flex-1 truncate text-sm text-slate-400">{missing.label}</span>
                            <Link
                                href={createUrlFor(missing.path, categoriesBySlug)}
                                className="shrink-0 rounded-lg px-2 py-0.5 text-[11px] font-medium text-teal-700 opacity-0 ring-1 ring-inset ring-teal-200 transition group-hover:opacity-100"
                            >
                                + Créer
                            </Link>
                        </div>
                    </li>
                ))}

                {column.nodes.length === 0 && column.missing.length === 0 && (
                    <li className="px-2 py-6 text-center text-[11px] text-slate-400">Fin de la branche.</li>
                )}
            </ul>
        </div>
    );
}

function BranchPanel({ node, trail, creatives, categoriesBySlug, onVariation }) {
    if (!node) {
        return (
            <div className="rounded-2xl border border-dashed border-slate-300 bg-white/60 px-6 py-12 text-center">
                <p className="text-3xl" aria-hidden>
                    👈
                </p>
                <p className="mt-2 text-sm font-medium text-slate-700">Choisissez une branche</p>
                <p className="mt-1 text-xs text-slate-500">
                    Cliquez dans la première colonne pour descendre dans l&apos;arbre.
                </p>
            </div>
        );
    }

    const isWinner = node.winners > 0;
    const path = trail.map((step) => ({
        axis: step.axis,
        value_id: step.value_id,
        label: step.label,
    }));

    return (
        <section className={`rounded-2xl border bg-white ${isWinner ? 'border-emerald-300' : 'border-slate-200'}`}>
            <header
                className={`flex flex-wrap items-start justify-between gap-3 rounded-t-2xl border-b px-4 py-3 ${
                    isWinner ? 'border-emerald-100 bg-emerald-50/60' : 'border-slate-100 bg-slate-50/60'
                }`}
            >
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        {isWinner ? '🏆 Branche gagnante' : node.creatives > 0 ? '🌿 Branche testée' : '⚪ Branche vide'}
                    </p>
                    <h2 className="mt-0.5 text-base font-semibold text-slate-900">
                        {trail.map((step) => step.label).join(' · ')}
                    </h2>
                    <p className="mt-0.5 text-xs text-slate-600">
                        {node.creatives} créa{node.creatives > 1 ? 's' : ''} · {node.live} en ligne · {node.winners} winner
                        {node.winners > 1 ? 's' : ''}
                        {node.cost_per_qualified !== null && ` · ${euro(node.cost_per_qualified)} / lead qualifié`}
                    </p>
                </div>

                <Button as="link" href={createUrlFor(path, categoriesBySlug)} size="sm">
                    + Créer une créa ici
                </Button>
            </header>

            <div className="grid gap-3 p-3 sm:grid-cols-2">
                {creatives.map((creative) => (
                    <CreativeCard key={creative.id} creative={creative} onVariation={onVariation} />
                ))}
            </div>

            {creatives.length === 0 && (
                <p className="px-4 pb-6 text-center text-xs text-slate-500">
                    Rien n&apos;a encore été testé exactement ici. C&apos;est une opportunité.
                </p>
            )}
        </section>
    );
}

function OpportunityPanel({ gaps, categoriesBySlug }) {
    return (
        <aside className="h-fit rounded-2xl border border-amber-200 bg-amber-50/50">
            <header className="border-b border-amber-100 px-4 py-3">
                <h2 className="text-sm font-semibold text-slate-800">🔥 À tester ensuite</h2>
                <p className="text-[11px] text-slate-500">Les voisins directs de ce qui marche déjà.</p>
            </header>

            <ul className="max-h-[32rem] divide-y divide-amber-100 overflow-y-auto">
                {gaps.slice(0, 12).map((gap, index) => (
                    <li key={index} className="px-4 py-3">
                        <p className="text-xs leading-relaxed text-slate-700">{gap.label}</p>
                        <p className="mt-0.5 text-[11px] text-slate-500">
                            {gap.axis_label} non testé · {gap.sibling_creatives} créa(s) à côté
                            {gap.sibling_winners > 0 && ` · 🏆 ${gap.sibling_winners}`}
                        </p>
                        <Link
                            href={createUrlFor(gap.path, categoriesBySlug)}
                            className="mt-2 inline-flex rounded-lg bg-teal-700 px-2.5 py-1 text-[11px] font-medium text-white transition hover:bg-teal-800"
                        >
                            Créer
                        </Link>
                    </li>
                ))}
                {gaps.length === 0 && <li className="px-4 py-6 text-center text-xs text-slate-500">Tout est couvert.</li>}
            </ul>
        </aside>
    );
}

function Key({ children }) {
    return (
        <kbd className="rounded border border-slate-300 bg-white px-1 font-sans text-[10px] text-slate-500">{children}</kbd>
    );
}

function Stat({ icon, value, label }) {
    return (
        <div className="flex items-center gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
            <span className="text-lg" aria-hidden>
                {icon}
            </span>
            <span>
                <span className="block text-lg leading-none font-semibold tabular-nums text-slate-900">{value}</span>
                <span className="text-[11px] text-slate-500">{label}</span>
            </span>
        </div>
    );
}
