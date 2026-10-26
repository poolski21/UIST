import { useForm, usePage } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

export default function Notes({ matieres = [], etudiants = [], annees = [], notes = [] }) {
    const { auth, locale = 'fr' } = usePage().props;
    const isTeacher = auth.user.role === 'teacher';
    const form = useForm({ matiere_id: '', etudiant_id: '', annee_academique_id: '', notes_td: [''], note_examen: '' });
    const submit = (event) => { event.preventDefault(); form.post(isTeacher ? '/enseignant/notes' : '/admin/notes'); };
    const draftGroups = [...new Map(notes.filter((note) => note.statut === 'brouillon').map((note) => [`${note.matiere_id}-${note.annee_academique_id}`, note])).values()];
    const updateTd = (index, value) => form.setData('notes_td', form.data.notes_td.map((note, itemIndex) => itemIndex === index ? value : note));
    const addTd = () => form.setData('notes_td', [...form.data.notes_td, '']);
    const removeTd = (index) => form.setData('notes_td', form.data.notes_td.filter((_, itemIndex) => itemIndex !== index));

    return <PortalLayout title={isTeacher ? 'Saisie des notes' : 'Gestion des notes'}>
        <h1 className="mb-6 text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t(isTeacher ? 'Saisie des notes' : 'Gestion des notes', locale)}</h1>
        <form onSubmit={submit} className="mb-8 grid gap-4 rounded-xl bg-white p-6 shadow-sm md:grid-cols-2 xl:grid-cols-3">
            <label className="text-sm font-medium">{t('Matière', locale)}<select required value={form.data.matiere_id} onChange={(e) => form.setData('matiere_id', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2"><option value="">{t('Choisir…', locale)}</option>{matieres.map((x) => <option key={x.id} value={x.id}>{x.nom}</option>)}</select></label>
            <label className="text-sm font-medium">{t('Étudiant', locale)}<select required value={form.data.etudiant_id} onChange={(e) => form.setData('etudiant_id', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2"><option value="">{t('Choisir…', locale)}</option>{etudiants.map((x) => <option key={x.id} value={x.id}>{x.name} ({x.matricule})</option>)}</select></label>
            <label className="text-sm font-medium">{t('Année', locale)}<select required value={form.data.annee_academique_id} onChange={(e) => form.setData('annee_academique_id', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2"><option value="">{t('Choisir…', locale)}</option>{annees.map((x) => <option key={x.id} value={x.id}>{x.libelle}</option>)}</select></label>
            <fieldset className="rounded-lg border p-3 md:col-span-2"><legend className="px-1 text-sm font-semibold">{t('Notes de TD', locale)}</legend><div className="grid gap-3 sm:grid-cols-2">{form.data.notes_td.map((note, index) => <label key={index} className="text-sm font-medium">{t('TD', locale)} {index + 1}<div className="mt-1 flex gap-2"><input required min="0" max="20" step="0.01" type="number" value={note} onChange={(event) => updateTd(index, event.target.value)} className="w-full rounded-lg border px-3 py-2" />{form.data.notes_td.length > 1 && <button type="button" onClick={() => removeTd(index)} aria-label={`${t('Supprimer TD', locale)} ${index + 1}`} className="rounded-lg border px-3 text-slate-600">×</button>}</div></label>)}</div><button type="button" onClick={addTd} disabled={form.data.notes_td.length >= 20} className="mt-3 text-sm font-semibold text-[#1a93e8]">{t('Ajouter une note de TD', locale)}</button></fieldset>
            <label className="text-sm font-medium">{t('Examen final / 20', locale)}<input required min="0" max="20" step="0.01" type="number" value={form.data.note_examen} onChange={(e) => form.setData('note_examen', e.target.value)} className="mt-1 w-full rounded-lg border px-3 py-2" /></label>
            <p className="self-center text-sm text-slate-500">{t('Moyenne matière : moyenne des TD (40 %) + examen final (60 %). Validation si la moyenne est supérieure ou égale à 10/20.', locale)}</p>
            <button disabled={form.processing} className="self-end rounded-lg bg-[#1a93e8] px-4 py-2 font-semibold text-white">{t('Enregistrer en brouillon', locale)}</button>
            {Object.entries(form.errors).map(([field, error]) => <p key={field} className="text-sm text-red-600 md:col-span-2">{error}</p>)}
        </form>
        {!isTeacher && <section className="mb-7 rounded-xl bg-white p-6 shadow-sm"><h2 className="mb-3 font-bold text-[#692333]">{t('Notes à publier', locale)}</h2><div className="flex flex-wrap gap-3">
            {draftGroups.map((note) => <form key={`${note.matiere_id}-${note.annee_academique_id}`} method="post" action={`/admin/notes/${note.matiere_id}/${note.annee_academique_id}/publier`} className="rounded-lg border p-3"><input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content} /><span className="mr-3 text-sm">{note.matiere?.nom} · {note.annee_academique?.libelle}</span><button className="text-sm font-semibold text-[#1a93e8]">{t('Publier les brouillons', locale)}</button></form>)}
            {!draftGroups.length && <p className="text-sm text-slate-500">{t('Aucune note en brouillon.', locale)}</p>}
        </div></section>}
        <section className="overflow-hidden rounded-xl bg-white shadow-sm"><div className="border-b px-6 py-4"><h2 className="font-bold text-[#692333]">{t('Notes enregistrées', locale)}</h2></div><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs uppercase text-slate-500"><tr><th className="px-5 py-3">{t('Étudiant', locale)}</th><th className="px-5 py-3">{t('Matière', locale)}</th><th className="px-5 py-3">{t('Année', locale)}</th><th className="px-5 py-3">{t('Notes / résultat', locale)}</th><th className="px-5 py-3">{t('Statut', locale)}</th>{!isTeacher && <th className="px-5 py-3">{t('Correction administrative', locale)}</th>}</tr></thead><tbody>{notes.map((note) => <tr key={note.id} className="border-t"><td className="px-5 py-3">{note.etudiant?.name}<small className="block text-slate-400">{note.etudiant?.matricule}</small></td><td className="px-5 py-3">{note.matiere?.nom}</td><td className="px-5 py-3">{note.annee_academique?.libelle}</td><td className="px-5 py-3"><strong>{Number(note.note).toFixed(2)} / 20</strong>{note.notes_td?.length > 0 && <small className="block text-slate-500">{t('TD', locale)} : {note.notes_td.map((score) => Number(score).toFixed(2)).join(' · ')} · {t('Examen', locale)} : {Number(note.note_examen).toFixed(2)}</small>}{note.notes_td?.length > 0 && <small className={`block font-semibold ${Number(note.note) >= 10 ? 'text-emerald-700' : 'text-rose-700'}`}>{t(Number(note.note) >= 10 ? 'Matière validée' : 'Matière non validée', locale)}</small>}</td><td className="px-5 py-3"><span className={`rounded-full px-2.5 py-1 text-xs ${note.statut === 'publie' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>{t(note.statut, locale)}</span></td>{!isTeacher && <td className="px-5 py-3">{note.statut === 'publie' ? <PublishedNoteCorrection note={note} locale={locale} /> : '—'}</td>}</tr>)}</tbody></table></div></section>
    </PortalLayout>;
}

function PublishedNoteCorrection({ note, locale }) {
    const form = useForm({
        notes_td: note.notes_td?.length ? note.notes_td.map(String) : [''],
        note_examen: note.note_examen ?? '',
        motif: '',
    });
    const submit = (event) => {
        event.preventDefault();
        form.put(`/admin/notes/${note.id}/correction`);
    };

    return <form onSubmit={submit} className="min-w-64 space-y-2">
        {form.data.notes_td.map((score, index) => <label key={index} className="block text-xs font-medium">{t('TD', locale)} {index + 1}<input required min="0" max="20" step="0.01" type="number" value={score} onChange={(event) => form.setData('notes_td', form.data.notes_td.map((value, item) => item === index ? event.target.value : value))} className="mt-1 w-full rounded-lg border px-2 py-1.5" /></label>)}
        <label className="block text-xs font-medium">{t('Examen final / 20', locale)}<input required min="0" max="20" step="0.01" type="number" value={form.data.note_examen} onChange={(event) => form.setData('note_examen', event.target.value)} className="mt-1 w-full rounded-lg border px-2 py-1.5" /></label>
        <label className="block text-xs font-medium">{t('Motif de correction', locale)}<textarea required value={form.data.motif} onChange={(event) => form.setData('motif', event.target.value)} className="mt-1 w-full rounded-lg border px-2 py-1.5" rows="2" /></label>
        <button disabled={form.processing} className="rounded-lg border px-3 py-2 text-xs font-semibold text-[#1a93e8]">{t('Corriger la note publiée', locale)}</button>
        {Object.values(form.errors).map((error) => <p key={error} className="text-xs text-red-600">{error}</p>)}
    </form>;
}
