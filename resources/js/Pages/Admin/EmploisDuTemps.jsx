import { useForm, usePage } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

export default function EmploisDuTemps({ emplois = [], filieres = [], niveaux = [] }) {
    const { locale = 'fr' } = usePage().props;
    const form = useForm({ titre: '', description: '', filiere_id: '', niveau_id: '', date_cours: '', heure_debut: '08:00', heure_fin: '10:00', salle: '', publier: true });
    const submit = (event) => { event.preventDefault(); form.post('/admin/emplois-du-temps'); };
    return <PortalLayout title="Emplois du temps">
        <h1 className="mb-6 text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Emplois du temps', locale)}</h1>
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <form onSubmit={submit} className="space-y-4 rounded-xl bg-white p-6 shadow-sm">
                <h2 className="text-lg font-bold text-[#692333]">{t('Ajouter un cours planifié', locale)}</h2>
                <label className="block text-sm font-medium">{t('Intitulé', locale)}<input required value={form.data.titre} onChange={(e) => form.setData('titre', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2" /></label>
                <label className="block text-sm font-medium">{t('Description', locale)}<textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2" /></label>
                <div className="grid grid-cols-2 gap-3">
                    <label className="text-sm font-medium">{t('Filière', locale)}<select value={form.data.filiere_id} onChange={(e) => form.setData('filiere_id', e.target.value)} className="mt-1 w-full rounded-lg border px-2 py-2"><option value="">{t('Toutes', locale)}</option>{filieres.map((x) => <option key={x.id} value={x.id}>{x.nom}</option>)}</select></label>
                    <label className="text-sm font-medium">{t('Niveau', locale)}<select value={form.data.niveau_id} onChange={(e) => form.setData('niveau_id', e.target.value)} className="mt-1 w-full rounded-lg border px-2 py-2"><option value="">{t('Tous', locale)}</option>{niveaux.filter((x) => !form.data.filiere_id || String(x.filiere_id) === String(form.data.filiere_id)).map((x) => <option key={x.id} value={x.id}>{x.nom}</option>)}</select></label>
                </div>
                <div className="grid grid-cols-2 gap-3"><label className="text-sm font-medium">{t('Date', locale)}<input type="date" required value={form.data.date_cours} onChange={(e) => form.setData('date_cours', e.target.value)} className="mt-1 w-full rounded-lg border px-2 py-2" /></label><label className="text-sm font-medium">{t('Salle', locale)}<input value={form.data.salle} onChange={(e) => form.setData('salle', e.target.value)} className="mt-1 w-full rounded-lg border px-2 py-2" /></label></div>
                <div className="grid grid-cols-2 gap-3"><label className="text-sm font-medium">{t('Début', locale)}<input type="time" required value={form.data.heure_debut} onChange={(e) => form.setData('heure_debut', e.target.value)} className="mt-1 w-full rounded-lg border px-2 py-2" /></label><label className="text-sm font-medium">{t('Fin', locale)}<input type="time" required value={form.data.heure_fin} onChange={(e) => form.setData('heure_fin', e.target.value)} className="mt-1 w-full rounded-lg border px-2 py-2" /></label></div>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.publier} onChange={(e) => form.setData('publier', e.target.checked)} />{t('Publier immédiatement', locale)}</label>
                {Object.values(form.errors).map((error) => <p key={error} className="text-sm text-red-600">{error}</p>)}
                <button className="rounded-lg bg-[#1a93e8] px-5 py-2.5 font-semibold text-white">{t('Enregistrer', locale)}</button>
            </form>
            <section className="rounded-xl bg-white p-6 shadow-sm"><h2 className="mb-4 text-lg font-bold text-[#692333]">{t('Planning publié et brouillons', locale)}</h2><div className="space-y-3">
                {emplois.map((item) => <article key={item.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"><div><p className="font-semibold">{item.titre}</p><p className="text-sm text-slate-500">{new Date(`${item.date_cours}T00:00:00`).toLocaleDateString(locale === 'en' ? 'en-GB' : 'fr-FR')} · {item.heure_debut.slice(0, 5)}–{item.heure_fin.slice(0, 5)} · {t(item.salle || 'Salle à confirmer', locale)}</p><p className="text-xs text-slate-400">{t(item.filiere?.nom ?? 'Toutes filières', locale)} · {t(item.niveau?.nom ?? 'Tous niveaux', locale)} · {t(item.statut, locale)}</p></div>
                    {item.statut !== 'publie' && <form method="post" action={`/admin/emplois-du-temps/${item.id}/publier`}><input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content} /><button className="text-sm font-semibold text-[#1a93e8]">{t('Publier', locale)}</button></form>}</article>)}
                {!emplois.length && <p className="text-sm text-slate-500">{t('Aucun cours planifié.', locale)}</p>}</div></section>
        </div>
    </PortalLayout>;
}
