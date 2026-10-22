import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

const DAY_NAMES = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const DAY_SHORT_NAMES = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
const FIRST_MINUTE = 8 * 60;
const LAST_MINUTE = 16 * 60 + 30;
const SLOT_MINUTES = 30;
const SLOT_COUNT = (LAST_MINUTE - FIRST_MINUTE) / SLOT_MINUTES;
const SLOT_HEIGHT = 28;
const GRID_ROWS = `40px repeat(${SLOT_COUNT}, ${SLOT_HEIGHT}px)`;
const SUBJECT_COLORS = [
    'border-sky-600 bg-sky-50 text-sky-950 hover:bg-sky-100',
    'border-violet-600 bg-violet-50 text-violet-950 hover:bg-violet-100',
    'border-emerald-600 bg-emerald-50 text-emerald-950 hover:bg-emerald-100',
    'border-amber-600 bg-amber-50 text-amber-950 hover:bg-amber-100',
    'border-rose-600 bg-rose-50 text-rose-950 hover:bg-rose-100',
    'border-cyan-600 bg-cyan-50 text-cyan-950 hover:bg-cyan-100',
];

function timeToMinutes(value) {
    const [hours, minutes] = String(value ?? '').split(':').map(Number);
    return hours * 60 + minutes;
}

function minutesToTime(value) {
    const hours = Math.floor(value / 60);
    const minutes = value % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

function dateForDay(weekStart, day) {
    const date = new Date(`${weekStart}T12:00:00`);
    date.setDate(date.getDate() + Number(day) - 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function mondayForDate(value) {
    const date = new Date(`${value}T12:00:00`);
    date.setDate(date.getDate() - ((date.getDay() + 6) % 7));
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function normalizeTime(value) {
    return String(value ?? '').slice(0, 5);
}

function overlaps(start, end, existingStart, existingEnd) {
    return start < timeToMinutes(existingEnd) && end > timeToMinutes(existingStart);
}

function subjectColor(emploi) {
    const subjectKey = String(emploi.matiere_id ?? emploi.titre ?? emploi.id);
    const hash = Array.from(subjectKey).reduce((value, character) => value + character.charCodeAt(0), 0);

    return SUBJECT_COLORS[hash % SUBJECT_COLORS.length];
}

export default function EmploisDuTemps({
    emplois = [],
    conflits = [],
    filieres = [],
    niveaux = [],
    matieres = [],
    enseignants = [],
    selection = {},
}) {
    const { locale = 'fr' } = usePage().props;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm({
        filiere_id: selection.filiere_id ?? '',
        niveau_id: selection.niveau_id ?? '',
        week_start: selection.week_start ?? '',
        jour: 1,
        matiere_id: '',
        enseignant_id: '',
        description: '',
        heure_debut: '08:00',
        heure_fin: '09:30',
        salle: '',
        publier: false,
    });
    const weekPublish = useForm({
        filiere_id: selection.filiere_id ?? '',
        niveau_id: selection.niveau_id ?? '',
        week_start: selection.week_start ?? '',
    });

    const hasClass = Boolean(selection.filiere_id && selection.niveau_id);
    const drafts = emplois.filter((emploi) => emploi.statut !== 'publie');
    const published = emplois.length > 0 && drafts.length === 0;
    const status = published ? t('Publié', locale) : t('Brouillon', locale);

    function updateFilters(nextFilters) {
        router.get('/admin/emplois-du-temps', nextFilters, {
            preserveScroll: true,
            replace: true,
        });
    }

    function moveWeek(offset) {
        const week = new Date(`${selection.week_start}T12:00:00`);
        week.setDate(week.getDate() + offset * 7);

        updateFilters({
            filiere_id: selection.filiere_id,
            niveau_id: selection.niveau_id,
            week_start: `${week.getFullYear()}-${String(week.getMonth() + 1).padStart(2, '0')}-${String(week.getDate()).padStart(2, '0')}`,
        });
    }

    function openCreate(day, startMinute) {
        if (!hasClass) {
            return;
        }

        const endMinute = Math.min(startMinute + 90, LAST_MINUTE);
        setEditing(null);
        form.clearErrors();
        form.setData({
            filiere_id: selection.filiere_id,
            niveau_id: selection.niveau_id,
            week_start: selection.week_start,
            jour: day,
            matiere_id: '',
            enseignant_id: '',
            description: '',
            heure_debut: minutesToTime(startMinute),
            heure_fin: minutesToTime(endMinute),
            salle: '',
            publier: false,
        });
        setDialogOpen(true);
    }

    function openEdit(emploi) {
        const weekStart = selection.week_start;
        const date = String(emploi.date_cours).slice(0, 10);
        const day = Math.round((new Date(`${date}T12:00:00`) - new Date(`${weekStart}T12:00:00`)) / 86400000) + 1;

        setEditing(emploi);
        form.clearErrors();
        form.setData({
            filiere_id: String(emploi.filiere_id),
            niveau_id: String(emploi.niveau_id),
            week_start: weekStart,
            jour: day,
            matiere_id: String(emploi.matiere_id ?? ''),
            enseignant_id: String(emploi.enseignant_id ?? ''),
            description: emploi.description ?? '',
            heure_debut: normalizeTime(emploi.heure_debut),
            heure_fin: normalizeTime(emploi.heure_fin),
            salle: emploi.salle ?? '',
            publier: false,
        });
        setDialogOpen(true);
    }

    function setSubject(subjectId) {
        const subject = matieres.find((item) => String(item.id) === String(subjectId));
        form.setData({
            ...form.data,
            matiere_id: subjectId,
            enseignant_id: subject?.enseignant_id ? String(subject.enseignant_id) : '',
        });
    }

    function validateClient() {
        form.clearErrors();

        const start = timeToMinutes(form.data.heure_debut);
        const end = timeToMinutes(form.data.heure_fin);

        if (!Number.isFinite(start) || !Number.isFinite(end)) {
            form.setError('heure_debut', t('Les horaires doivent être au format HH:MM.', locale));
            return false;
        }
        if (start < FIRST_MINUTE) {
            form.setError('heure_debut', t('Une séance ne peut pas commencer avant 08:00.', locale));
            return false;
        }
        if (end > LAST_MINUTE) {
            form.setError('heure_fin', t('Une séance ne peut pas se terminer après 16:30.', locale));
            return false;
        }
        if (end <= start) {
            form.setError('heure_fin', t('L’heure de fin doit être strictement après l’heure de début.', locale));
            return false;
        }

        const date = dateForDay(form.data.week_start, form.data.jour);
        const room = form.data.salle.trim();
        const candidates = conflits.filter((existing) => {
            if (Number(existing.id) === Number(editing?.id)) {
                return false;
            }

            return String(existing.date_cours).slice(0, 10) === date
                && overlaps(start, end, existing.heure_debut, existing.heure_fin);
        });

        const classConflict = candidates.some((existing) => (
            String(existing.filiere_id) === String(form.data.filiere_id)
            && String(existing.niveau_id) === String(form.data.niveau_id)
        ));
        if (classConflict) {
            form.setError('jour', t('Une autre séance de cette classe chevauche déjà cet horaire.', locale));
            return false;
        }

        const teacherConflict = form.data.enseignant_id && candidates.some((existing) => (
            String(existing.enseignant_id ?? '') === String(form.data.enseignant_id)
        ));
        if (teacherConflict) {
            form.setError('enseignant_id', t('Cet enseignant a déjà une séance qui chevauche cet horaire.', locale));
            return false;
        }

        const roomConflict = room && candidates.some((existing) => (
            String(existing.salle ?? '') === room
        ));
        if (roomConflict) {
            form.setError('salle', t('Cette salle est déjà occupée pendant cet horaire.', locale));
            return false;
        }

        return true;
    }

    function submit(event) {
        event.preventDefault();
        if (!validateClient()) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                setEditing(null);
                form.reset();
            },
        };

        if (editing) {
            form.put(`/admin/emplois-du-temps/${editing.id}`, options);
        } else {
            form.post('/admin/emplois-du-temps', options);
        }
    }

    function removeSession(emploi) {
        if (window.confirm(t('Confirmer la suppression de cette séance ?', locale))) {
            router.delete(`/admin/emplois-du-temps/${emploi.id}`, {
                preserveScroll: true,
                onSuccess: () => setDialogOpen(false),
            });
        }
    }

    function publishWeek() {
        weekPublish.transform(() => ({
            filiere_id: selection.filiere_id,
            niveau_id: selection.niveau_id,
            week_start: selection.week_start,
        }));
        weekPublish.post('/admin/emplois-du-temps/publier-semaine', { preserveScroll: true });
    }

    const className = (emploi) => emploi.matiere?.nom ?? emploi.titre;
    const slotTimes = Array.from({ length: SLOT_COUNT }, (_, index) => FIRST_MINUTE + index * SLOT_MINUTES);

    return (
        <PortalLayout title="Emplois du temps">
            <h1 className="mb-3 text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Emplois du temps', locale)}</h1>

            <section className="mx-auto mb-3 grid max-w-6xl grid-cols-2 items-end gap-2 rounded-lg bg-white p-2 shadow-sm sm:p-3 lg:flex">
                <label className="min-w-0 text-xs font-medium text-slate-700 lg:w-44">
                    <span className="sr-only">{t('Filière', locale)}</span>
                    <select
                        value={selection.filiere_id ?? ''}
                        onChange={(event) => updateFilters({
                            filiere_id: event.target.value,
                            niveau_id: '',
                            week_start: selection.week_start,
                        })}
                        aria-label={t('Filière', locale)}
                        className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs"
                    >
                        <option value="">{t('Sélectionner une filière', locale)}</option>
                        {filieres.map((filiere) => <option key={filiere.id} value={filiere.id}>{filiere.nom}</option>)}
                    </select>
                </label>
                <label className="min-w-0 text-xs font-medium text-slate-700 lg:w-36">
                    <span className="sr-only">{t('Niveau', locale)}</span>
                    <select
                        value={selection.niveau_id ?? ''}
                        onChange={(event) => updateFilters({
                            filiere_id: selection.filiere_id,
                            niveau_id: event.target.value,
                            week_start: selection.week_start,
                        })}
                        disabled={!selection.filiere_id}
                        aria-label={t('Niveau', locale)}
                        className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs disabled:bg-slate-100"
                    >
                        <option value="">{t('Sélectionner un niveau', locale)}</option>
                        {niveaux
                            .filter((niveau) => String(niveau.filiere_id) === String(selection.filiere_id))
                            .map((niveau) => <option key={niveau.id} value={niveau.id}>{niveau.nom}</option>)}
                    </select>
                </label>
                <div className="col-span-2 flex min-w-0 items-end gap-1 lg:w-[270px]">
                    <button
                        type="button"
                        aria-label={t('Semaine précédente', locale)}
                        title={t('Semaine précédente', locale)}
                        onClick={() => moveWeek(-1)}
                        className="h-[34px] w-8 shrink-0 rounded-md border border-slate-300 text-sm text-slate-700 hover:bg-slate-50"
                    >
                        ←
                    </button>
                    <label className="min-w-0 flex-1 text-xs font-medium text-slate-700">
                        <span className="sr-only">{t('Semaine (lundi)', locale)}</span>
                        <input
                            type="date"
                            value={selection.week_start ?? ''}
                            aria-label={t('Semaine (lundi)', locale)}
                            onChange={(event) => {
                                if (event.target.value) {
                                    updateFilters({
                                        filiere_id: selection.filiere_id,
                                        niveau_id: selection.niveau_id,
                                        week_start: mondayForDate(event.target.value),
                                    });
                                }
                            }}
                            className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs"
                        />
                    </label>
                    <button
                        type="button"
                        aria-label={t('Semaine suivante', locale)}
                        title={t('Semaine suivante', locale)}
                        onClick={() => moveWeek(1)}
                        className="h-[34px] w-8 shrink-0 rounded-md border border-slate-300 text-sm text-slate-700 hover:bg-slate-50"
                    >
                        →
                    </button>
                </div>
                {hasClass && (
                    <div className="col-span-2 flex items-center justify-between gap-2 lg:ml-auto lg:justify-end">
                        <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${published ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>
                            {status}
                        </span>
                        <button
                            type="button"
                            disabled={drafts.length === 0 || weekPublish.processing}
                            onClick={publishWeek}
                            className="min-h-8 rounded-md bg-[#1a93e8] px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {t('Publier la semaine', locale)}
                        </button>
                    </div>
                )}
            </section>

            {!hasClass ? (
                <p className="mx-auto max-w-6xl rounded-lg border border-sky-100 bg-sky-50 p-4 text-sm text-sky-900">
                    {t('Choisissez une filière et un niveau pour afficher la grille.', locale)}
                </p>
            ) : (
                <section className="mx-auto max-w-6xl rounded-lg border border-slate-200 bg-white p-1.5 sm:p-2">
                    <div className="overflow-x-auto">
                        <div
                            className="grid min-w-[640px] lg:min-w-0"
                            style={{ gridTemplateColumns: '56px repeat(6, minmax(0, 1fr))', gridTemplateRows: GRID_ROWS }}
                        >
                            <div className="sticky top-0 left-0 z-30 border-b border-r border-slate-200 bg-slate-50" style={{ gridColumn: 1, gridRow: 1 }} />
                            {DAY_NAMES.map((day, index) => {
                                const date = new Date(`${dateForDay(selection.week_start, index + 1)}T12:00:00`);
                                const shortDate = new Intl.DateTimeFormat(locale === 'en' ? 'en-GB' : 'fr-FR', {
                                    day: '2-digit',
                                    month: '2-digit',
                                }).format(date);

                                return (
                                    <div
                                        key={day}
                                        className="sticky top-0 z-20 flex flex-col items-center justify-center border-b border-r border-slate-200 bg-slate-50 py-1 leading-tight text-slate-700"
                                        style={{ gridColumn: index + 2, gridRow: 1 }}
                                    >
                                        <span className="text-xs font-semibold">{t(DAY_SHORT_NAMES[index], locale)}</span>
                                        <span className="text-[10px] text-slate-500">{shortDate}</span>
                                    </div>
                                );
                            })}

                            {slotTimes.map((minute, index) => (
                                <div
                                    key={minute}
                                    className={`sticky left-0 z-10 border-r border-slate-200 bg-white pr-1 text-right text-xs leading-none text-slate-500 ${minute % 60 === 0 ? 'border-t border-slate-300 pt-0.5' : 'border-t border-slate-100'}`}
                                    style={{ gridColumn: 1, gridRow: index + 2 }}
                                >
                                    {minute % 60 === 0 ? minutesToTime(minute) : ''}
                                </div>
                            ))}

                            {DAY_NAMES.map((day, index) => {
                                const dayNumber = index + 1;
                                const daySessions = emplois.filter((emploi) => (
                                    String(emploi.date_cours).slice(0, 10) === dateForDay(selection.week_start, dayNumber)
                                ));

                                return (
                                    <div
                                        key={day}
                                        className="relative border-r border-slate-200"
                                        style={{ gridColumn: index + 2, gridRow: `2 / span ${SLOT_COUNT}` }}
                                    >
                                        {slotTimes.map((minute, slotIndex) => (
                                            <button
                                                key={minute}
                                                type="button"
                                                aria-label={`${t('Ajouter une séance', locale)} · ${t(day, locale)} ${minutesToTime(minute)}`}
                                                onClick={() => openCreate(dayNumber, minute)}
                                                className={`absolute left-0 w-full cursor-pointer border-t text-left transition-colors ${minute % 60 === 0 ? 'border-slate-300' : 'border-slate-200'} hover:bg-sky-50`}
                                                style={{ top: `${slotIndex * SLOT_HEIGHT}px`, height: `${SLOT_HEIGHT}px` }}
                                            />
                                        ))}

                                        {daySessions.map((emploi) => {
                                            const start = timeToMinutes(emploi.heure_debut);
                                            const end = timeToMinutes(emploi.heure_fin);
                                            const teacherName = emploi.enseignant?.name ?? t('Aucun enseignant défini', locale);
                                            const roomName = emploi.salle || t('Aucune salle définie', locale);
                                            const details = `${className(emploi)} · ${teacherName} · ${roomName} · ${normalizeTime(emploi.heure_debut)}–${normalizeTime(emploi.heure_fin)}`;

                                            return (
                                                <button
                                                    key={emploi.id}
                                                    type="button"
                                                    title={details}
                                                    onClick={() => openEdit(emploi)}
                                                    className={`absolute z-10 flex w-[calc(100%-3px)] cursor-pointer flex-col overflow-hidden rounded-r border-y border-r border-l-[3px] px-1 py-0.5 text-left transition-colors ${subjectColor(emploi)} ${emploi.statut === 'publie' ? 'border-solid' : 'border-dashed'}`}
                                                    style={{
                                                        top: `${(start - FIRST_MINUTE) * (SLOT_HEIGHT / SLOT_MINUTES)}px`,
                                                        height: `${(end - start) * (SLOT_HEIGHT / SLOT_MINUTES)}px`,
                                                        minHeight: `${SLOT_HEIGHT}px`,
                                                    }}
                                                >
                                                    <span className="truncate text-xs font-bold leading-tight">{className(emploi)}</span>
                                                    <span className="truncate text-[11px] leading-tight">{teacherName} · {roomName}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                    {weekPublish.errors && Object.values(weekPublish.errors).map((error) => (
                        <p key={error} role="alert" className="mt-2 text-sm text-red-600">{t(error, locale)}</p>
                    ))}
                </section>
            )}

            {dialogOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4" role="presentation">
                    <section
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="schedule-dialog-title"
                        className="my-auto max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-4 shadow-xl sm:p-5"
                    >
                        <h2 id="schedule-dialog-title" className="mb-3 text-lg font-bold text-[#692333]">
                            {t(editing ? 'Modifier une séance' : 'Ajouter une séance', locale)}
                        </h2>
                        <form onSubmit={submit} className="space-y-2.5">
                            <label className="block text-xs font-medium text-slate-700">
                                {t('Matière', locale)}
                                <select
                                    required
                                    value={form.data.matiere_id}
                                    onChange={(event) => setSubject(event.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm"
                                >
                                    <option value="">{t('Veuillez choisir une matière.', locale)}</option>
                                    {matieres.map((matiere) => <option key={matiere.id} value={matiere.id}>{matiere.nom}</option>)}
                                </select>
                            </label>
                            <label className="block text-xs font-medium text-slate-700">
                                {t('Enseignant', locale)}
                                <select
                                    value={form.data.enseignant_id}
                                    onChange={(event) => form.setData('enseignant_id', event.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm"
                                >
                                    <option value="">{t('Aucun enseignant défini', locale)}</option>
                                    {enseignants.map((enseignant) => <option key={enseignant.id} value={enseignant.id}>{enseignant.name}</option>)}
                                </select>
                                {form.errors.enseignant_id && <span className="mt-1 block text-sm text-red-600">{t(form.errors.enseignant_id, locale)}</span>}
                            </label>
                            <label className="block text-xs font-medium text-slate-700">
                                {t('Jour', locale)}
                                <select
                                    value={form.data.jour}
                                    onChange={(event) => form.setData('jour', Number(event.target.value))}
                                    className="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm"
                                >
                                    {DAY_NAMES.map((day, index) => <option key={day} value={index + 1}>{t(day, locale)}</option>)}
                                </select>
                            </label>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="text-xs font-medium text-slate-700">
                                    {t('Début', locale)}
                                    <input
                                        required
                                        type="time"
                                        min="08:00"
                                        max="16:30"
                                        value={form.data.heure_debut}
                                        onChange={(event) => form.setData('heure_debut', event.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm"
                                    />
                                    {form.errors.heure_debut && <span className="mt-1 block text-sm text-red-600">{t(form.errors.heure_debut, locale)}</span>}
                                </label>
                                <label className="text-xs font-medium text-slate-700">
                                    {t('Fin', locale)}
                                    <input
                                        required
                                        type="time"
                                        min="08:00"
                                        max="16:30"
                                        value={form.data.heure_fin}
                                        onChange={(event) => form.setData('heure_fin', event.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm"
                                    />
                                    {form.errors.heure_fin && <span className="mt-1 block text-sm text-red-600">{t(form.errors.heure_fin, locale)}</span>}
                                </label>
                            </div>
                            <label className="block text-xs font-medium text-slate-700">
                                {t('Salle', locale)}
                                <input
                                    value={form.data.salle}
                                    onChange={(event) => form.setData('salle', event.target.value)}
                                    maxLength="100"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm"
                                />
                                {form.errors.salle && <span className="mt-1 block text-sm text-red-600">{t(form.errors.salle, locale)}</span>}
                            </label>
                            <label className="block text-xs font-medium text-slate-700">
                                {t('Description', locale)}
                                <textarea
                                    value={form.data.description}
                                    onChange={(event) => form.setData('description', event.target.value)}
                                    rows="2"
                                    className="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm"
                                />
                            </label>
                            {!editing && (
                                <label className="flex items-center gap-2 text-xs text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={form.data.publier}
                                        onChange={(event) => form.setData('publier', event.target.checked)}
                                    />
                                    {t('Publier immédiatement', locale)}
                                </label>
                            )}
                            {form.errors.jour && <p role="alert" className="text-sm text-red-600">{t(form.errors.jour, locale)}</p>}
                            {Object.entries(form.errors)
                                .filter(([field]) => !['enseignant_id', 'heure_debut', 'heure_fin', 'salle', 'jour'].includes(field))
                                .map(([field, error]) => <p key={field} role="alert" className="text-sm text-red-600">{t(error, locale)}</p>)}
                            <div className="flex justify-end gap-1.5 pt-1">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="min-h-8 rounded-md bg-[#1a93e8] px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 disabled:opacity-50"
                                >
                                    {t('Enregistrer', locale)}
                                </button>
                                {editing && (
                                    <button
                                        type="button"
                                        onClick={() => removeSession(editing)}
                                        className="min-h-8 rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                    >
                                        {t('Supprimer', locale)}
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => setDialogOpen(false)}
                                    className="min-h-8 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    {t('Annuler', locale)}
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            )}
        </PortalLayout>
    );
}
