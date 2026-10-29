import { Head, useForm, usePage } from '@inertiajs/react';
import { translate as t } from '../../i18n';

export default function ChangePassword() {
    const { locale = 'fr' } = usePage().props;
    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();
        form.put('/securite/mot-de-passe');
    }

    return (
        <main className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">
            <Head title={t('Changer mon mot de passe', locale)} />
            <section className="w-full max-w-lg space-y-5 rounded-xl bg-white p-5 shadow-sm sm:p-8">
                <header className="space-y-2">
                    <p className="text-sm font-semibold text-sky-700">UIST2its</p>
                    <h1 className="text-2xl font-bold text-slate-900">
                        {t('Changer mon mot de passe', locale)}
                    </h1>
                    <p className="text-sm text-slate-600">
                        {t('Vous devez définir un nouveau mot de passe pour continuer.', locale)}
                    </p>
                </header>

                <form onSubmit={submit} className="space-y-4">
                    <label className="block text-sm font-medium text-slate-700">
                        {t('Mot de passe actuel', locale)}
                        <input
                            type="password"
                            autoComplete="current-password"
                            required
                            value={form.data.current_password}
                            onChange={(event) => form.setData('current_password', event.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"
                        />
                    </label>
                    {form.errors.current_password && (
                        <p role="alert" className="text-sm text-red-600">{form.errors.current_password}</p>
                    )}

                    <label className="block text-sm font-medium text-slate-700">
                        {t('Nouveau mot de passe (16 caractères minimum)', locale)}
                        <input
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"
                        />
                    </label>
                    {form.errors.password && (
                        <p role="alert" className="text-sm text-red-600">{form.errors.password}</p>
                    )}

                    <label className="block text-sm font-medium text-slate-700">
                        {t('Confirmer le nouveau mot de passe', locale)}
                        <input
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"
                        />
                    </label>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="min-h-11 w-full rounded-lg bg-sky-700 px-4 py-2.5 font-semibold text-white hover:bg-sky-800 disabled:opacity-60"
                    >
                        {form.processing ? t('Enregistrement…', locale) : t('Enregistrer', locale)}
                    </button>
                </form>
            </section>
        </main>
    );
}
