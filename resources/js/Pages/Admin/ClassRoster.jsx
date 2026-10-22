import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

export default function ClassRoster({ annees = [], filieres = [], niveaux = [], selection = {}, etudiants = [], pdfUrl = null }) {
    const { locale = 'fr' } = usePage().props;
    const [filters, setFilters] = useState({
        annee_academique_id: selection.annee_academique_id ?? '',
        filiere_id: selection.filiere_id ?? '',
        niveau_id: selection.niveau_id ?? '',
    });
    const submit = (event) => {
        event.preventDefault();
        router.get('/admin/listes-classe', filters, { preserveState: true, preserveScroll: true });
    };

    return <PortalLayout title="Listes de classe">
        <h1 className="mb-6 text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Listes de classe', locale)}</h1>
        <form onSubmit={submit} className="mb-6 grid gap-4 rounded-xl bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <label className="text-sm font-medium">{t('Année universitaire', locale)}
                <select required value={filters.annee_academique_id} onChange={(event) => setFilters({ ...filters, annee_academique_id: event.target.value })} className="mt-1 w-full rounded-lg border px-3 py-2">
                    <option value="">{t('Choisir une année', locale)}</option>
                    {annees.map((annee) => <option key={annee.id} value={annee.id}>{annee.libelle}{annee.active ? ` (${t('en cours', locale)})` : ''}</option>)}
                </select>
            </label>
            <label className="text-sm font-medium">{t('Filière', locale)}
                <select required value={filters.filiere_id} onChange={(event) => setFilters({ ...filters, filiere_id: event.target.value, niveau_id: '' })} className="mt-1 w-full rounded-lg border px-3 py-2">
                    <option value="">{t('Choisir une filière', locale)}</option>
                    {filieres.map((filiere) => <option key={filiere.id} value={filiere.id}>{filiere.nom}</option>)}
                </select>
            </label>
            <label className="text-sm font-medium">{t('Niveau', locale)}
                <select required value={filters.niveau_id} onChange={(event) => setFilters({ ...filters, niveau_id: event.target.value })} className="mt-1 w-full rounded-lg border px-3 py-2">
                    <option value="">{t('Choisir un niveau', locale)}</option>
                    {niveaux.filter((niveau) => String(niveau.filiere_id) === String(filters.filiere_id)).map((niveau) => <option key={niveau.id} value={niveau.id}>{niveau.nom}</option>)}
                </select>
            </label>
            <div className="flex items-end gap-3">
                <button className="rounded-lg bg-[#1a93e8] px-5 py-2.5 font-semibold text-white">{t('Afficher', locale)}</button>
                {pdfUrl && <a href={pdfUrl} className="rounded-lg border border-[#1a93e8] px-4 py-2.5 text-sm font-semibold text-[#1a93e8]">{t('Télécharger PDF', locale)}</a>}
            </div>
        </form>
        {filters.filiere_id && filters.niveau_id && <section className="overflow-hidden rounded-xl bg-white shadow-sm">
            <div className="border-b px-5 py-4"><h2 className="font-bold text-[#692333]">{t('Étudiants inscrits', locale)} · {etudiants.length}</h2></div>
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="bg-sky-50 text-slate-700"><tr><th className="px-5 py-3">{t('Matricule permanent', locale)}</th><th className="px-5 py-3">{t('Nom', locale)}</th><th className="px-5 py-3">{t('Prénom', locale)}</th></tr></thead>
                    <tbody>{etudiants.map((etudiant) => <tr key={etudiant.id} className="border-t"><td className="px-5 py-3">{etudiant.matricule}</td><td className="px-5 py-3">{etudiant.nom}</td><td className="px-5 py-3">{etudiant.prenom}</td></tr>)}
                    {!etudiants.length && <tr><td colSpan="3" className="px-5 py-6 text-center text-slate-500">{t('Aucune inscription trouvée pour cette sélection.', locale)}</td></tr>}</tbody>
                </table>
            </div>
        </section>}
        <Link href="/admin" className="mt-5 inline-block text-sm font-semibold text-[#1a93e8]">{t('Retour au tableau de bord', locale)}</Link>
    </PortalLayout>;
}
