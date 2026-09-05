import { Link } from '@inertiajs/react';
import { iconFor } from '../lib/icons';
import { euro, num } from '../lib/format';
import { Badge, RatingBadge } from './Ui';

/**
 * The reusable visual representation of a creative — the idea on top,
 * the execution in the middle, the numbers at the bottom.
 */
export default function CreativeCard({ creative, compact = false, onVariation = null, footer = null }) {
    const persona = creative.persona ?? [];

    const identity = [
        creative.product?.code,
        ...persona.filter((chip) => ['gender', 'age'].includes(chip.category_slug)).map((chip) => chip.label),
    ].filter(Boolean);

    const angle = persona.filter((chip) =>
        ['specific-problem', 'trigger', 'motivation'].includes(chip.category_slug),
    );

    const hasNumbers = creative.metrics?.leads > 0 || creative.metrics?.spend > 0;

    return (
        <article className="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs transition hover:-translate-y-0.5 hover:border-teal-300 hover:shadow-md">
            <Link href={`/creatives/${creative.id}`} className="block p-4">
                <div className="flex items-start justify-between gap-2">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        {identity.join(' · ') || 'Sans cible'}
                    </p>
                    {creative.status && <Badge color={creative.status.color}>{creative.status.name}</Badge>}
                </div>

                <div className="mt-2 space-y-1">
                    {angle.slice(0, 3).map((chip) => (
                        <p key={`${chip.category_slug}-${chip.value_id}`} className="flex items-center gap-1.5 text-sm text-slate-800">
                            <span aria-hidden>{iconFor(chip.category_slug, chip.code)}</span>
                            <span className="truncate">{chip.label}</span>
                        </p>
                    ))}
                    {angle.length === 0 && <p className="text-sm text-slate-400">Angle à définir</p>}
                </div>

                {creative.hook && !compact && (
                    <p className="mt-3 line-clamp-3 border-l-2 border-teal-200 pl-2.5 text-sm italic text-slate-600">
                        « {creative.hook} »
                    </p>
                )}

                <div className="mt-3 flex flex-wrap items-center gap-1.5">
                    <span className="font-mono text-[10px] text-slate-400">{creative.reference}</span>
                    {creative.channels?.length > 0 && (
                        <span className="text-[11px] text-slate-500">· {creative.channels.map((c) => c.name).join(' · ')}</span>
                    )}
                </div>
            </Link>

            {(hasNumbers || footer || onVariation) && (
                <div className="mt-auto border-t border-slate-100 px-4 py-2.5">
                    {hasNumbers && (
                        <div className="mb-2 grid grid-cols-2 gap-x-2 gap-y-1 text-center sm:grid-cols-4">
                            <Figure label="Leads" value={num(creative.metrics.leads)} />
                            <Figure label="Qual." value={num(creative.metrics.qualified_leads)} />
                            <Figure label="Conf." value={num(creative.metrics.confirmed)} />
                            <Figure label="Coût/QL" value={euro(creative.metrics.cost_per_qualified)} />
                        </div>
                    )}

                    <div className="flex items-center justify-between gap-2">
                        <RatingBadge rating={creative.rating} manual={creative.rating_is_manual} />
                        {onVariation && (
                            <button
                                type="button"
                                onClick={() => onVariation(creative)}
                                className="rounded-lg px-2 py-1 text-[11px] font-medium text-teal-700 ring-1 ring-inset ring-teal-200 transition hover:bg-teal-50"
                            >
                                Créer une variation
                            </button>
                        )}
                        {footer}
                    </div>
                </div>
            )}
        </article>
    );
}

function Figure({ label, value }) {
    return (
        <div>
            <p className="truncate text-[10px] uppercase tracking-wide text-slate-400">{label}</p>
            <p className="truncate text-sm font-semibold tabular-nums text-slate-800">{value}</p>
        </div>
    );
}
