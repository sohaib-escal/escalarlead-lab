/** Small visual vocabulary so branches and cards are scannable, not just readable. */

export const CATEGORY_ICONS = {
    product: '📦',
    gender: '👤',
    age: '🎂',
    household: '🏡',
    'property-type': '🏠',
    'house-age': '🧱',
    'ownership-duration': '🔑',
    'area-type': '🗺️',
    income: '💶',
    'aid-awareness': '📄',
    'aid-eligibility': '✅',
    'heating-system': '🔥',
    'electricity-situation': '⚡',
    'existing-solar': '☀️',
    consumption: '📈',
    awareness: '🧠',
    problem: '❗',
    'specific-problem': '💸',
    symptom: '🩺',
    trigger: '⏰',
    motivation: '🎯',
    objection: '🛑',
    employment: '💼',
    retirement: '🌤️',
    homeowner: '🔑',
    channel: '📣',
};

export const VALUE_ICONS = {
    M: '👨',
    W: '👩',
    HIGHBILL: '💸',
    OLDBOILER: '🕰️',
    BREAKDOWN: '🧯',
    COLDHOUSE: '🥶',
    ELECBILL: '⚡',
    ROOF: '☀️',
    DRAFTS: '🌬️',
    CONDENS: '💧',
    NOISE: '🔊',
    OLDWIN: '🪟',
    WINTER: '❄️',
    BILL: '🧾',
    BROKE: '🧯',
    RENO: '🔨',
    HEARDAID: '📢',
    PRICEUP: '📈',
    SAVE: '💰',
    COMFORT: '🛋️',
    REPLACE: '🔧',
    INDEP: '🔋',
    VALUE: '📐',
    AID: '💰',
    PAC: '🌡️',
    SOLAR: '☀️',
    DV: '🪟',
};

export const iconFor = (categorySlug, code) => VALUE_ICONS[code] ?? CATEGORY_ICONS[categorySlug] ?? '•';

/**
 * How well a branch has been explored. Drives the dot the user scans for.
 * 🏆 winner · 🔥 strong · 🟢 tested · 🟡 light · ⚪ untested
 */
export const coverageOf = (node) => {
    if (!node || node.creatives === 0) return { key: 'untested', icon: '⚪', label: 'Non testé', tone: 'text-slate-400' };
    if (node.winners > 0) return { key: 'winner', icon: '🏆', label: 'Branche gagnante', tone: 'text-emerald-600' };

    // Launched is not the same as measured: say so instead of implying a verdict.
    if (!node.spend && !node.leads) {
        return { key: 'no_data', icon: '🧪', label: 'Testé, pas encore mesuré', tone: 'text-violet-500' };
    }

    if (node.creatives >= 3) return { key: 'strong', icon: '🔥', label: 'Bien testé', tone: 'text-orange-500' };
    if (node.live > 0) return { key: 'tested', icon: '🟢', label: 'Test en cours', tone: 'text-emerald-500' };
    return { key: 'light', icon: '🟡', label: 'Peu testé', tone: 'text-amber-500' };
};

export const GENERATION_STATUS = {
    queued: { label: 'EN FILE', icon: '⏳', tone: 'bg-slate-100 text-slate-700' },
    generating: { label: 'GÉNÉRATION', icon: '🎬', tone: 'bg-amber-100 text-amber-800' },
    completed: { label: 'TERMINÉ', icon: '✅', tone: 'bg-emerald-100 text-emerald-800' },
    failed: { label: 'ÉCHEC', icon: '⚠️', tone: 'bg-rose-100 text-rose-800' },
    awaiting_manual: { label: 'À GÉNÉRER DANS FLOW', icon: '🎞️', tone: 'bg-sky-100 text-sky-800' },
};
