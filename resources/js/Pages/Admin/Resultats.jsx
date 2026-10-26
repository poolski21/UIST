import { useForm, usePage } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

export default function Resultats({ resultats = [], etudiants = [], annees = [] }) {
    const { locale = 'fr' } = usePage().props;
    const calculate = useForm({ etudiant_id: '', annee_id: '' });
    const submitCalculation = (event) => { event.preventDefault(); calculate.post(`/admin/resultats/calculer/${calculate.data.etudiant_id}/${calculate.data.annee_id}`); };

    return <PortalLayout title="Décisions annuelles">
        <h1 className="mb-6 text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Validation de l’année universitaire', locale)}</h1>
        <form onSubmit={submitCalculation} className="mb-7 flex flex-wrap items-end gap-4 rounded-xl bg-white p-4 shadow-sm sm:p-6">
            <div className="w-full sm:mr-auto sm:w-auto"><h2 className="font-bold text-[#692333]">{t('Calcul automatique', locale)}</h2><p className="text-sm text-slate-500">{t('Moyenne pondérée et crédits des matières publiées.', locale)}</p></div>
            <label className="w-full text-sm font-medium sm:w-auto">{t('Étudiant', locale)}<select required value={calculate.data.etudiant_id} onChange={(e) => calculate.setData('etudiant_id', e.target.value)} className="mt-1 block w-full rounded-lg border px-3 py-2 sm:min-w-56"><option value="">{t('Choisir…', locale)}</option>{etudiants.map((x) => <option key={x.id} value={x.id}>{x.name} · {x.matricule}</option>)}</select></label>
            <label className="w-full text-sm font-medium sm:w-auto">{t('Année', locale)}<select required value={calculate.data.annee_id} onChange={(e) => calculate.setData('annee_id', e.target.value)} className="mt-1 block w-full rounded-lg border px-3 py-2 sm:min-w-44"><option value="">{t('Choisir…', locale)}</option>{annees.map((x) => <option key={x.id} value={x.id}>{x.libelle}</option>)}</select></label>
            <button disabled={calculate.processing || !calculate.data.etudiant_id || !calculate.data.annee_id} className="rounded-lg bg-[#1a93e8] px-5 py-2.5 font-semibold text-white">{t('Calculer', locale)}</button>
            {Object.values(calculate.errors).map((error) => <p key={error} className="w-full text-sm text-red-600">{error}</p>)}
        </form>
        <section className="space-y-4">{resultats.map((resultat) => <ResultRow key={resultat.id} resultat={resultat} />)}
            {!resultats.length && <p className="rounded-xl bg-white p-6 text-sm text-slate-500">{t('Aucun résultat calculé.', locale)}</p>}
        </section>
    </PortalLayout>;
}

function ResultRow({ resultat }) {
    const { locale = 'fr' } = usePage().props;
    const form = useForm({ decision_finale: resultat.decision_finale ?? resultat.decision_auto, motif: '' });
    const decide = (event) => { event.preventDefault(); form.put(`/admin/resultats/${resultat.id}/decision`); };
    return <article className="rounded-xl bg-white p-5 shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div><h2 className="font-bold text-slate-800">{resultat.etudiant?.name} · {resultat.etudiant?.matricule}</h2><p className="mt-1 text-sm text-slate-500">{resultat.annee_academique?.libelle} · {t('Moyenne', locale)} {Number(resultat.moyenne_generale).toFixed(2)} / 20 · {resultat.credits_obtenus} {t('crédits', locale)}</p><p className="mt-1 text-sm">{t('Décision proposée :', locale)} <strong>{t(resultat.decision_auto, locale)}</strong>{resultat.decision_finale && <> · {t('Décision finale :', locale)} <strong>{t(resultat.decision_finale, locale)}</strong></>}</p>{resultat.motif && <p className="mt-1 text-xs text-slate-500">{t('Motif', locale)}: {resultat.motif}</p>}</div>
            {resultat.a_recalculer && <p className="text-sm font-semibold text-amber-700">{t('Résultat à recalculer', locale)}</p>}
            {!resultat.publie_le && <form method="post" action={`/admin/resultats/${resultat.id}/publier`}><input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content} /><button disabled={resultat.a_recalculer} className="rounded-lg border px-3 py-2 text-sm font-semibold text-[#1a93e8]">{t('Publier la décision', locale)}</button></form>}
        </div>
        <form onSubmit={decide} className="mt-4 flex flex-wrap items-end gap-3 border-t pt-4">
            <label className="text-sm font-medium">{t('Décision finale', locale)}<select value={form.data.decision_finale} onChange={(e) => form.setData('decision_finale', e.target.value)} className="mt-1 block rounded-lg border px-3 py-2"><option value="admis">{t('Admis', locale)}</option><option value="ajourne">{t('Ajourné', locale)}</option></select></label>
            <label className="w-full min-w-0 flex-1 text-sm font-medium sm:w-auto">{t('Motif obligatoire', locale)}<input required value={form.data.motif} onChange={(e) => form.setData('motif', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2" placeholder={t('Justification de la décision finale', locale)} /></label>
            <button className="rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white">{t('Enregistrer la décision', locale)}</button>
            {Object.values(form.errors).map((error) => <p key={error} className="w-full text-sm text-red-600">{error}</p>)}
        </form>
    </article>;
}
