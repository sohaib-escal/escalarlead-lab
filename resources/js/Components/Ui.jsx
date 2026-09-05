import { Link } from '@inertiajs/react';
import { COLOR_STYLES, RATING_LABELS, RATING_STYLES } from '../lib/format';

export function Badge({ children, color = 'slate', className = '' }) {
    return (
        <span
            className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${
                COLOR_STYLES[color] ?? COLOR_STYLES.slate
            } ${className}`}
        >
            {children}
        </span>
    );
}

export function RatingBadge({ rating, manual = false }) {
    return (
        <span
            className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold tracking-wide ring-1 ring-inset ${
                RATING_STYLES[rating] ?? RATING_STYLES.no_data
            }`}
            title={manual ? 'Indicateur forcé manuellement' : 'Indicateur calculé sur le coût par lead qualifié'}
        >
            {rating === 'winner' && <span aria-hidden>🏆</span>}
            {RATING_LABELS[rating] ?? rating}
            {manual && <span className="opacity-60">·</span>}
        </span>
    );
}

export function Card({ title, action, children, className = '', bodyClassName = 'p-4' }) {
    return (
        <section className={`rounded-xl border border-slate-200 bg-white shadow-xs ${className}`}>
            {(title || action) && (
                <header className="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <h2 className="text-sm font-semibold text-slate-800">{title}</h2>
                    {action}
                </header>
            )}
            <div className={bodyClassName}>{children}</div>
        </section>
    );
}

export function StatCard({ label, value, hint, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900',
        good: 'text-emerald-600',
        warn: 'text-amber-600',
        bad: 'text-rose-600',
    };

    return (
        <div className="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-xs">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`mt-1 text-2xl font-semibold tabular-nums ${tones[tone]}`}>{value}</p>
            {hint && <p className="mt-0.5 text-xs text-slate-500">{hint}</p>}
        </div>
    );
}

export function Metric({ label, value, strong = false }) {
    return (
        <div className="flex items-baseline justify-between gap-3 border-b border-dashed border-slate-100 py-1.5 last:border-0">
            <span className={`text-xs ${strong ? 'font-semibold text-slate-700' : 'text-slate-500'}`}>{label}</span>
            <span className={`tabular-nums ${strong ? 'text-sm font-semibold text-slate-900' : 'text-sm text-slate-700'}`}>{value}</span>
        </div>
    );
}

export function Button({ as = 'button', variant = 'primary', size = 'md', className = '', ...props }) {
    const variants = {
        primary: 'bg-teal-700 text-white hover:bg-teal-800 focus-visible:outline-teal-700',
        secondary: 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50',
        ghost: 'text-slate-600 hover:bg-slate-100',
        danger: 'bg-rose-600 text-white hover:bg-rose-700',
    };
    const sizes = {
        sm: 'px-2.5 py-1.5 text-xs',
        md: 'px-3 py-2 text-sm',
    };

    const classes = `inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition disabled:cursor-not-allowed disabled:opacity-50 ${
        variants[variant]
    } ${sizes[size]} ${className}`;

    if (as === 'link') {
        return <Link className={classes} {...props} />;
    }

    if (as === 'a') {
        return <a className={classes} {...props} />;
    }

    return <button className={classes} {...props} />;
}

export function Field({ label, error, hint, children, className = '' }) {
    return (
        <label className={`block ${className}`}>
            {label && <span className="mb-1 block text-xs font-medium text-slate-600">{label}</span>}
            {children}
            {hint && !error && <span className="mt-1 block text-[11px] text-slate-400">{hint}</span>}
            {error && <span className="mt-1 block text-[11px] text-rose-600">{error}</span>}
        </label>
    );
}

const controlClasses =
    'w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 shadow-xs ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-teal-600';

export function Input({ className = '', ...props }) {
    return <input className={`${controlClasses} ${className}`} {...props} />;
}

export function Textarea({ className = '', rows = 4, ...props }) {
    return <textarea rows={rows} className={`${controlClasses} ${className}`} {...props} />;
}

export function Select({ className = '', children, ...props }) {
    return (
        <select className={`${controlClasses} ${className}`} {...props}>
            {children}
        </select>
    );
}

export function Toggle({ checked, onChange, label }) {
    return (
        <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
            <input
                type="checkbox"
                checked={!!checked}
                onChange={(e) => onChange(e.target.checked)}
                className="size-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600"
            />
            {label}
        </label>
    );
}

/**
 * Every empty state answers three things: what this area is, why it matters,
 * and the one obvious next move. Never "aucune donnée".
 */
export function EmptyState({ icon = '🌱', title, description, action, className = '' }) {
    return (
        <div
            className={`flex flex-col items-center justify-center gap-1.5 rounded-2xl border border-dashed border-slate-300 bg-white/60 px-6 py-10 text-center ${className}`}
        >
            <span className="text-2xl" aria-hidden>
                {icon}
            </span>
            <p className="text-sm font-medium text-slate-700">{title}</p>
            {description && <p className="max-w-md text-xs leading-relaxed text-slate-500">{description}</p>}
            {action && <div className="mt-1">{action}</div>}
        </div>
    );
}

export function Table({ head, children, className = '' }) {
    return (
        <div className={`overflow-x-auto ${className}`}>
            <table className="min-w-full text-left text-sm">
                <thead className="border-b border-slate-200 bg-slate-50/80 text-[11px] uppercase tracking-wide text-slate-500">
                    <tr>
                        {head.map((cell, i) => (
                            <th key={i} scope="col" className={`px-3 py-2 font-semibold ${cell.align === 'right' ? 'text-right' : ''}`}>
                                {cell.label ?? cell}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">{children}</tbody>
            </table>
        </div>
    );
}

export function CopyButton({ value, label = 'Copier' }) {
    return (
        <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => {
                navigator.clipboard?.writeText(value ?? '');
            }}
            disabled={!value}
        >
            {label}
        </Button>
    );
}

export function Progress({ value, max = 100, tone = 'bg-teal-600', className = '' }) {
    const pct = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;

    return (
        <div className={`h-2 w-full overflow-hidden rounded-full bg-slate-100 ${className}`}>
            <div className={`h-full rounded-full transition-all duration-500 ${tone}`} style={{ width: `${pct}%` }} />
        </div>
    );
}

/** A coverage meter: how much of a branch we have actually explored. */
export function Coverage({ tested, total, label = 'Couverture' }) {
    const pct = total > 0 ? Math.round((tested / total) * 100) : 0;

    return (
        <div>
            <div className="mb-1 flex items-baseline justify-between">
                <span className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{label}</span>
                <span className="text-sm font-semibold tabular-nums text-slate-800">{pct} %</span>
            </div>
            <Progress value={tested} max={total || 1} />
            <p className="mt-1 text-[11px] text-slate-500">
                {tested} testées · {Math.max(0, total - tested)} à tester
            </p>
        </div>
    );
}

export function Modal({ open, onClose, title, children, width = 'max-w-lg' }) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 backdrop-blur-sm">
            <div className={`mt-16 w-full ${width} rounded-2xl bg-white shadow-xl`}>
                <header className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 className="text-sm font-semibold text-slate-800">{title}</h2>
                    <button type="button" onClick={onClose} className="rounded-lg px-2 text-slate-400 hover:bg-slate-100">
                        ×
                    </button>
                </header>
                <div className="p-4">{children}</div>
            </div>
        </div>
    );
}
