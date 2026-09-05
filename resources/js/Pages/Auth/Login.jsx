import { Head, useForm } from '@inertiajs/react';

export default function Login({ demoAccounts }) {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: false });

    const submit = (event) => {
        event.preventDefault();
        post('/login');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-100 px-4">
            <Head title="Connexion" />
            <div className="w-full max-w-sm">
                <div className="mb-6 flex items-center gap-2">
                    <span className="grid size-9 place-items-center rounded-lg bg-teal-700 text-sm font-bold text-white">CT</span>
                    <div>
                        <p className="text-sm font-semibold text-slate-900">Creative Tree</p>
                        <p className="text-xs text-slate-500">Rénovation énergétique — France</p>
                    </div>
                </div>

                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <label className="mb-3 block">
                        <span className="mb-1 block text-xs font-medium text-slate-600">Email</span>
                        <input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoFocus
                            className="w-full rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-teal-600"
                        />
                        {errors.email && <span className="mt-1 block text-[11px] text-rose-600">{errors.email}</span>}
                    </label>

                    <label className="mb-4 block">
                        <span className="mb-1 block text-xs font-medium text-slate-600">Mot de passe</span>
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="w-full rounded-lg border-0 px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-teal-600"
                        />
                    </label>

                    <label className="mb-4 flex items-center gap-2 text-xs text-slate-600">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="size-4 rounded border-slate-300 text-teal-700"
                        />
                        Rester connecté
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-lg bg-teal-700 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50"
                    >
                        Se connecter
                    </button>
                </form>

                <div className="mt-4 rounded-xl border border-slate-200 bg-white/70 p-3">
                    <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Comptes de démo</p>
                    <ul className="space-y-1">
                        {demoAccounts.map((account) => (
                            <li key={account.email}>
                                <button
                                    type="button"
                                    onClick={() => setData({ ...data, email: account.email, password: 'password' })}
                                    className="w-full rounded-md px-2 py-1 text-left text-xs text-slate-600 hover:bg-slate-100"
                                >
                                    <span className="font-medium text-slate-800">{account.role}</span> · {account.email}
                                </button>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-2 px-2 text-[11px] text-slate-400">Mot de passe : password</p>
                </div>
            </div>
        </div>
    );
}
