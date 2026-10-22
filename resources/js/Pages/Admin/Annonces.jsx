import { useForm, usePage } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

export default function Annonces({ annonces = [], filieres = [], niveaux = [] }) {
    const { locale = 'fr' } = usePage().props;
    const form = useForm({ titre: '', contenu: '', piece_jointe: null, cible_type: 'tous', cible_id: '', publier: true });
    const submit = (event) => { event.preventDefault(); form.post('/admin/annonces', { forceFormData: true, onSuccess: () => form.reset('titre', 'contenu', 'piece_jointe') }); };
    const targets = form.data.cible_type === 'filiere' ? filieres : niveaux;
    return <PortalLayout title="Annonces">
        <h1 className="mb-6 text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Annonces et communications', locale)}</h1>
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <form onSubmit={submit} className="space-y-4 rounded-xl bg-white p-6 shadow-sm">
                <h2 className="text-lg font-bold text-[#692333]">{t('Nouvelle annonce', locale)}</h2>
                <label className="block text-sm font-medium">{t('Titre', locale)}<input required value={form.data.titre} onChange={(e) => form.setData('titre', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2" /></label>
                <label className="block text-sm font-medium">{t('Contenu', locale)}<textarea required rows="6" value={form.data.contenu} onChange={(e) => form.setData('contenu', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2" /></label>
                <label className="block text-sm font-medium">{t('Document joint (PDF, Word ou image)', locale)}<input type="file" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" onChange={(e) => form.setData('piece_jointe', e.target.files[0])} className="mt-1 block w-full text-sm" /></label>
                <div className="grid gap-3 sm:grid-cols-2">
                    <label className="text-sm font-medium">{t('Destinataires', locale)}<select value={form.data.cible_type} onChange={(e) => form.setData({ ...form.data, cible_type: e.target.value, cible_id: '' })} className="mt-1 w-full rounded-lg border px-3 py-2"><option value="tous">{t('Tous les étudiants', locale)}</option><option value="filiere">{t('Une filière', locale)}</option><option value="niveau">{t('Un niveau', locale)}</option></select></label>
                    {form.data.cible_type !== 'tous' && <label className="text-sm font-medium">{t('Formation', locale)}<select required value={form.data.cible_id} onChange={(e) => form.setData('cible_id', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2"><option value="">{t('Choisir…', locale)}</option>{targets.map((item) => <option key={item.id} value={item.id}>{item.nom}</option>)}</select></label>}
                </div>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.publier} onChange={(e) => form.setData('publier', e.target.checked)} />{t('Publier immédiatement et notifier les étudiants', locale)}</label>
                {Object.values(form.errors).map((error) => <p key={error} className="text-sm text-red-600">{error}</p>)}
                <button disabled={form.processing} className="rounded-lg bg-[#1a93e8] px-5 py-2.5 font-semibold text-white">{t('Enregistrer', locale)}</button>
            </form>
            <section className="rounded-xl bg-white p-6 shadow-sm">
                <h2 className="mb-4 text-lg font-bold text-[#692333]">{t('Historique des annonces', locale)}</h2>
                <div className="space-y-3">{annonces.map((item) => <article key={item.id} className="rounded-lg border p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-semibold">{item.titre}</h3><p className="mt-1 text-xs text-slate-500">{item.cible_type === 'tous' ? t('Tous les étudiants', locale) : `${t('Cible', locale)} : ${t(item.cible_type, locale)}`} · {t(item.publiee_le ? 'Publiée' : 'Brouillon', locale)}</p></div>
                        {!item.publiee_le && <form method="post" action={`/admin/annonces/${item.id}/publier`}><input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content} /><button className="text-sm font-semibold text-[#1a93e8]">{t('Publier', locale)}</button></form>}</div>
                    <p className="mt-2 line-clamp-3 text-sm text-slate-600">{item.contenu}</p>
                </article>)}
                    {!annonces.length && <p className="text-sm text-slate-500">{t('Aucune annonce enregistrée.', locale)}</p>}
                </div>
            </section>
        </div>
    </PortalLayout>;
}
