export const euro = (value, options = {}) =>
    value === null || value === undefined
        ? '—'
        : new Intl.NumberFormat('fr-FR', {
              style: 'currency',
              currency: 'EUR',
              maximumFractionDigits: options.decimals ?? (value >= 1000 ? 0 : 2),
          }).format(value);

export const num = (value) =>
    value === null || value === undefined ? '—' : new Intl.NumberFormat('fr-FR').format(value);

export const pct = (value) => (value === null || value === undefined ? '—' : `${value.toFixed(2).replace('.', ',')} %`);

export const ratio = (value) => (value === null || value === undefined ? '—' : value.toFixed(2).replace('.', ','));

export const date = (value) =>
    value ? new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value)) : '—';

export const dateTime = (value) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
        : '—';

export const RATING_LABELS = {
    winner: 'WINNER',
    promising: 'PROMISING',
    average: 'AVERAGE',
    poor: 'POOR',
    testing: 'EN TEST',
    no_data: 'PAS DE DATA',
};

export const RATING_STYLES = {
    winner: 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
    promising: 'bg-sky-100 text-sky-800 ring-sky-600/20',
    average: 'bg-amber-100 text-amber-800 ring-amber-600/20',
    poor: 'bg-rose-100 text-rose-800 ring-rose-600/20',
    testing: 'bg-violet-100 text-violet-800 ring-violet-600/20',
    no_data: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};

export const COLOR_STYLES = {
    slate: 'bg-slate-100 text-slate-700 ring-slate-500/20',
    zinc: 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
    violet: 'bg-violet-100 text-violet-700 ring-violet-500/20',
    indigo: 'bg-indigo-100 text-indigo-700 ring-indigo-500/20',
    blue: 'bg-blue-100 text-blue-700 ring-blue-500/20',
    sky: 'bg-sky-100 text-sky-700 ring-sky-500/20',
    teal: 'bg-teal-100 text-teal-700 ring-teal-500/20',
    emerald: 'bg-emerald-100 text-emerald-700 ring-emerald-500/20',
    green: 'bg-green-100 text-green-700 ring-green-500/20',
    amber: 'bg-amber-100 text-amber-700 ring-amber-500/20',
    rose: 'bg-rose-100 text-rose-700 ring-rose-500/20',
};

export const CAMPAIGN_STATUS_LABELS = {
    draft: 'Brouillon',
    active: 'Active',
    paused: 'En pause',
    completed: 'Terminée',
    archived: 'Archivée',
};

export const CAMPAIGN_STATUS_STYLES = {
    draft: COLOR_STYLES.slate,
    active: COLOR_STYLES.emerald,
    paused: COLOR_STYLES.amber,
    completed: COLOR_STYLES.blue,
    archived: COLOR_STYLES.zinc,
};
