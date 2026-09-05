import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const NAV = [
    { href: '/creative-tree', label: 'Creative Tree', icon: '🌳', hero: true },
    { href: '/creatives', label: 'Créas', icon: '🎨' },
    { href: '/campaigns', label: 'Campagnes', icon: '📣' },
    { href: '/performance', label: 'Performance', icon: '📊' },
    { href: '/ai-studio', label: 'AI Studio', icon: '🤖' },
    { href: '/dashboard', label: 'Aperçu', icon: '👁️' },
    { href: '/admin', label: 'Admin', icon: '⚙️' },
];

export default function AppLayout({ children }) {
    const { auth, flash } = usePage().props;
    const currentUrl = usePage().url;
    const [search, setSearch] = useState('');
    const [notice, setNotice] = useState(null);
    const [navOpen, setNavOpen] = useState(false);

    useEffect(() => {
        const message = flash?.success || flash?.error;
        if (!message) return;
        setNotice({ type: flash?.success ? 'success' : 'error', message });
        const timer = setTimeout(() => setNotice(null), 5000);
        return () => clearTimeout(timer);
    }, [flash?.success, flash?.error]);

    const submitSearch = (event) => {
        event.preventDefault();
        router.get('/creatives', { search }, { preserveState: false });
    };

    return (
        <div className="min-h-full lg:flex">
            <aside className="border-b border-slate-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:w-56 lg:shrink-0 lg:border-r lg:border-b-0">
                <div className="flex items-center justify-between gap-2 px-4 py-4">
                    <Link href="/creative-tree" className="flex items-center gap-2">
                        <span className="grid size-8 place-items-center rounded-xl bg-teal-700 text-sm text-white">🌳</span>
                        <span className="text-sm font-semibold text-slate-800">
                            Creative Lab
                            <span className="block text-[11px] font-normal text-slate-500">Rénovation France</span>
                        </span>
                    </Link>
                    <button
                        type="button"
                        className="rounded-lg px-2 py-1 text-slate-500 ring-1 ring-slate-200 lg:hidden"
                        onClick={() => setNavOpen((open) => !open)}
                        aria-label="Menu"
                    >
                        ☰
                    </button>
                </div>

                <nav className={`${navOpen ? 'block' : 'hidden'} px-2 pb-4 lg:block`}>
                    {NAV.map((item) => {
                        const active = currentUrl.startsWith(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`mb-0.5 flex items-center gap-2.5 rounded-xl px-3 transition ${
                                    item.hero ? 'py-2.5 text-[15px] font-semibold' : 'py-2 text-sm'
                                } ${
                                    active
                                        ? 'bg-teal-50 text-teal-800'
                                        : item.hero
                                          ? 'text-slate-800 hover:bg-slate-100'
                                          : 'text-slate-600 hover:bg-slate-100'
                                }`}
                            >
                                <span className="w-5 text-center" aria-hidden>
                                    {item.icon}
                                </span>
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="hidden border-t border-slate-100 px-4 py-3 lg:block">
                    <div className="flex items-center gap-2">
                        <span className="grid size-8 place-items-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700">
                            {auth?.user?.initials}
                        </span>
                        <p className="min-w-0 flex-1 truncate text-xs font-medium text-slate-800">{auth?.user?.name}</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="mt-2 w-full rounded-lg px-2 py-1.5 text-left text-xs text-slate-500 hover:bg-slate-100"
                    >
                        Se déconnecter
                    </button>
                </div>
            </aside>

            <div className="min-w-0 flex-1">
                <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
                    <div className="flex items-center gap-3 px-4 py-2.5 sm:px-6">
                        <form onSubmit={submitSearch} className="max-w-md flex-1">
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Rechercher une créa, un hook, une campagne, un UTM…"
                                className="w-full rounded-xl border-0 bg-slate-100 px-3 py-1.5 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-inset focus:ring-teal-600"
                            />
                        </form>
                        <Link
                            href="/creatives/new"
                            className="rounded-xl bg-teal-700 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-teal-800"
                        >
                            + Nouvelle idée
                        </Link>
                    </div>
                </header>

                {notice && (
                    <div
                        className={`mx-4 mt-3 rounded-xl px-3 py-2 text-sm sm:mx-6 ${
                            notice.type === 'success'
                                ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-600/20'
                                : 'bg-rose-50 text-rose-800 ring-1 ring-rose-600/20'
                        }`}
                    >
                        {notice.message}
                    </div>
                )}

                <main className="px-4 py-5 sm:px-6">{children}</main>
            </div>
        </div>
    );
}
