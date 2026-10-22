import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

export default function Passwords({ admins = [], generatedPassword = null, mustChangePassword = false }) {
    const { locale = 'fr' } = usePage().props;
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const reset = useForm({});
    const [resettingId, setResettingId] = useState(null);

    const changePassword = (event) => {
        event.preventDefault();
        password.put('/admin/securite/mot-de-passe');
    };

    const resetPassword = (admin) => {
        setResettingId(admin.id);
        reset.post(`/admin/securite/mots-de-passe/${admin.id}`, {
            onFinish: () => setResettingId(null),
        });
    };

    return <PortalLayout title="Mots de passe administrateurs">
        <h1 className="mb-6 text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Mots de passe administrateurs', locale)}</h1>
        {generatedPassword && <section className="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-950 shadow-sm">
            <h2 className="font-bold">{t('Mot de passe temporaire — affiché une seule fois', locale)}</h2>
            <p className="my-2 text-sm">{t('Transmettez-le par un canal sûr. Le compte devra le remplacer avant toute autre action.', locale)}</p>
            <code className="block break-all rounded bg-white p-3 text-base">{generatedPassword}</code>
        </section>}
        {mustChangePassword && <section className="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-5 text-rose-900">
            {t('Vous devez remplacer votre mot de passe temporaire avant de continuer.', locale)}
        </section>}
        <div className="grid gap-6 lg:grid-cols-2">
            <form onSubmit={changePassword} className="space-y-4 rounded-xl bg-white p-6 shadow-sm">
                <h2 className="text-lg font-bold text-[#692333]">{t('Changer mon mot de passe', locale)}</h2>
                <label className="block text-sm font-medium">{t('Mot de passe actuel', locale)}<input type="password" autoComplete="current-password" required value={password.data.current_password} onChange={(event) => password.setData('current_password', event.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2" /></label>
                <label className="block text-sm font-medium">{t('Nouveau mot de passe (16 caractères minimum)', locale)}<input type="password" autoComplete="new-password" required value={password.data.password} onChange={(event) => password.setData('password', event.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2" /></label>
                <label className="block text-sm font-medium">{t('Confirmer le nouveau mot de passe', locale)}<input type="password" autoComplete="new-password" required value={password.data.password_confirmation} onChange={(event) => password.setData('password_confirmation', event.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2" /></label>
                {Object.values(password.errors).map((error) => <p key={error} className="text-sm text-red-600">{error}</p>)}
                <button disabled={password.processing} className="rounded-lg bg-[#1a93e8] px-5 py-2.5 font-semibold text-white disabled:opacity-60">{t('Enregistrer', locale)}</button>
            </form>
            <section className="rounded-xl bg-white p-6 shadow-sm">
                <h2 className="mb-2 text-lg font-bold text-[#692333]">{t('Réinitialiser un compte administrateur', locale)}</h2>
                <p className="mb-4 text-sm text-slate-600">{t('Un secret aléatoire sera affiché une seule fois et devra être remplacé à la prochaine connexion.', locale)}</p>
                <div className="space-y-3">
                    {admins.map((admin) => <article key={admin.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
                        <div><p className="font-semibold">{admin.name}</p><p className="text-sm text-slate-500">{admin.email}</p></div>
                        <button type="button" disabled={reset.processing} onClick={() => resetPassword(admin)} className="rounded-lg border border-[#1a93e8] px-3 py-2 text-sm font-semibold text-[#1a93e8] disabled:opacity-60">
                            {resettingId === admin.id ? t('Génération…', locale) : t('Générer un secret', locale)}
                        </button>
                    </article>)}
                    {!admins.length && <p className="text-sm text-slate-500">{t('Aucun compte administrateur.', locale)}</p>}
                </div>
            </section>
        </div>
    </PortalLayout>;
}
