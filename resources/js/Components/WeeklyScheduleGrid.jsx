import { router, usePage } from '@inertiajs/react';
import { translate as t } from '../i18n';

const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const FIRST_MINUTE = 8 * 60;
const LAST_MINUTE = 17 * 60;
const SLOT_MINUTES = 30;
const SLOT_HEIGHT = 24;
const SUBJECT_COLORS = [
    { hue: 215, name: 'bleu', classes: 'border-l-blue-700 bg-blue-50 text-blue-950' },
    { hue: 30, name: 'orange', classes: 'border-l-orange-700 bg-orange-50 text-orange-950' },
    { hue: 150, name: 'vert', classes: 'border-l-green-700 bg-green-50 text-green-950' },
    { hue: 330, name: 'rose', classes: 'border-l-pink-700 bg-pink-50 text-pink-950' },
    { hue: 185, name: 'cyan', classes: 'border-l-cyan-700 bg-cyan-50 text-cyan-950' },
    { hue: 5, name: 'rouge', classes: 'border-l-red-700 bg-red-50 text-red-950' },
    { hue: 270, name: 'violet', classes: 'border-l-violet-700 bg-violet-50 text-violet-950' },
    { hue: 50, name: 'jaune', classes: 'border-l-yellow-700 bg-yellow-50 text-yellow-950' },
    { hue: 165, name: 'turquoise', classes: 'border-l-teal-700 bg-teal-50 text-teal-950' },
    { hue: 300, name: 'fuchsia', classes: 'border-l-fuchsia-700 bg-fuchsia-50 text-fuchsia-950' },
    { hue: 240, name: 'indigo', classes: 'border-l-indigo-700 bg-indigo-50 text-indigo-950' },
    { hue: 90, name: 'lime', classes: 'border-l-lime-700 bg-lime-50 text-lime-950' },
];

function parseDate(value) {
    return new Date(`${value}T12:00:00`);
}

function dateToString(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function mondayForDate(value) {
    const date = new Date(value);
    date.setHours(12, 0, 0, 0);
    date.setDate(date.getDate() - ((date.getDay() + 6) % 7));

    return dateToString(date);
}

function dateForDay(weekStart, dayIndex) {
    const date = parseDate(weekStart);
    date.setDate(date.getDate() + dayIndex);

    return date;
}

function formatTime(value) {
    return String(value ?? '').slice(0, 5);
}

function timeToMinutes(value) {
    const [hours, minutes] = String(value ?? '').split(':').map(Number);

    return hours * 60 + minutes;
}

function subjectColor(course) {
    return SUBJECT_COLORS[course.subject_color_index ?? 0].classes;
}

function localDateString(date) {
    return dateToString(new Date(date.getFullYear(), date.getMonth(), date.getDate(), 12));
}

function layoutDayCourses(courses) {
    const sortedCourses = [...courses].sort((first, second) => (
        timeToMinutes(first.heure_debut) - timeToMinutes(second.heure_debut)
        || timeToMinutes(second.heure_fin) - timeToMinutes(first.heure_fin)
        || Number(first.id) - Number(second.id)
    ));
    const positionedCourses = [];
    let overlappingGroup = [];
    let overlappingGroupEnd = -Infinity;

    function positionGroup(group) {
        const laneEnds = [];
        const positionedGroup = group.map((course) => {
            const start = timeToMinutes(course.heure_debut);
            const end = timeToMinutes(course.heure_fin);
            let lane = laneEnds.findIndex((laneEnd) => laneEnd <= start);

            if (lane === -1) {
                lane = laneEnds.length;
            }
            laneEnds[lane] = end;

            return { ...course, layoutLane: lane };
        });

        return positionedGroup.map((course) => ({
            ...course,
            layoutLaneCount: laneEnds.length,
        }));
    }

    for (const course of sortedCourses) {
        const start = timeToMinutes(course.heure_debut);
        const end = timeToMinutes(course.heure_fin);

        if (overlappingGroup.length > 0 && start >= overlappingGroupEnd) {
            positionedCourses.push(...positionGroup(overlappingGroup));
            overlappingGroup = [];
            overlappingGroupEnd = -Infinity;
        }

        overlappingGroup.push(course);
        overlappingGroupEnd = Math.max(overlappingGroupEnd, end);
    }

    if (overlappingGroup.length > 0) {
        positionedCourses.push(...positionGroup(overlappingGroup));
    }

    return positionedCourses;
}

export default function WeeklyScheduleGrid({ courses = [], weekStart, isTeacher = false }) {
    const page = usePage();
    const { locale = 'fr' } = page.props;
    const { url = '/' } = page;
    const today = localDateString(new Date());
    const courseStart = courses.length
        ? Math.min(...courses.map((course) => timeToMinutes(course.heure_debut)))
        : FIRST_MINUTE;
    const courseEnd = courses.length
        ? Math.max(...courses.map((course) => timeToMinutes(course.heure_fin)))
        : LAST_MINUTE;
    const gridStart = Math.max(FIRST_MINUTE, Math.floor(courseStart / SLOT_MINUTES) * SLOT_MINUTES);
    const gridEnd = Math.min(LAST_MINUTE, Math.ceil(courseEnd / SLOT_MINUTES) * SLOT_MINUTES);
    const slotCount = Math.max(1, (gridEnd - gridStart) / SLOT_MINUTES);
    const weekEnd = dateForDay(weekStart, 5);
    const formatDate = (date) => new Intl.DateTimeFormat(locale === 'en' ? 'en-GB' : 'fr-FR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
    const formatDayDate = (date) => new Intl.DateTimeFormat(locale === 'en' ? 'en-GB' : 'fr-FR', {
        day: '2-digit',
        month: '2-digit',
    }).format(date);

    function navigateToWeek(targetWeek) {
        router.get(url.split('?')[0], { week_start: targetWeek }, {
            preserveScroll: true,
            replace: true,
        });
    }

    function moveWeek(offset) {
        const target = parseDate(weekStart);
        target.setDate(target.getDate() + (offset * 7));
        navigateToWeek(dateToString(target));
    }

    function renderCourse(course) {
        const start = Math.max(gridStart, timeToMinutes(course.heure_debut));
        const end = Math.min(gridEnd, timeToMinutes(course.heure_fin));

        if (end <= start) {
            return null;
        }

        const top = ((start - gridStart) / (gridEnd - gridStart)) * 100;
        const height = ((end - start) / (gridEnd - gridStart)) * 100;
        const laneCount = course.layoutLaneCount ?? 1;
        const lane = course.layoutLane ?? 0;
        const context = isTeacher
            ? [course.filiere, course.niveau].filter(Boolean).join(' · ')
            : course.enseignant;

        return (
            <article
                key={course.id}
                title={course.matiere ?? course.titre}
                className={`absolute z-10 overflow-hidden rounded-md border-l-[3px] p-1 text-left text-[11px] leading-tight shadow-sm transition-shadow hover:z-20 hover:shadow-md ${subjectColor(course)}`}
                style={{
                    top: `${top}%`,
                    left: `calc(${(lane * 100) / laneCount}% + 0.25rem)`,
                    width: `calc(${100 / laneCount}% - 0.5rem)`,
                    height: `${height}%`,
                    minHeight: `${SLOT_HEIGHT}px`,
                }}
            >
                <p className="line-clamp-3 break-words text-[11px] font-bold leading-tight">{course.matiere ?? course.titre}</p>
                <p className="truncate text-[10px]">{formatTime(course.heure_debut)}–{formatTime(course.heure_fin)}</p>
                {course.salle
                    ? <p className="truncate text-[10px]">{course.salle}</p>
                    : <p className="truncate text-[10px] italic text-gray-600" title="Salle à confirmer">Salle à conf.</p>}
                {context && <p className="truncate text-[10px]">{context}</p>}
            </article>
        );
    }

    return (
        <section aria-label={t(isTeacher ? 'Mon programme' : 'Emplois du temps', locale)} className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-sm font-bold text-slate-800 sm:text-base">
                    {t('Semaine du', locale)} {formatDate(parseDate(weekStart))} {t('au', locale)} {formatDate(weekEnd)}
                </h2>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        aria-label={t('Semaine précédente', locale)}
                        onClick={() => moveWeek(-1)}
                        className="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >← <span className="sr-only sm:not-sr-only">{t('Précédente', locale)}</span></button>
                    <button
                        type="button"
                        onClick={() => navigateToWeek(mondayForDate(new Date()))}
                        className="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >{t('Aujourd’hui', locale)}</button>
                    <button
                        type="button"
                        aria-label={t('Semaine suivante', locale)}
                        onClick={() => moveWeek(1)}
                        className="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    ><span className="sr-only sm:not-sr-only">{t('Suivante', locale)}</span> →</button>
                </div>
            </div>

            {!courses.length && <p className="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">{t('Aucun cours planifié cette semaine.', locale)}</p>}

            <div className="overflow-x-auto rounded-lg border border-slate-200">
                <div
                    className="grid min-w-[708px] grid-cols-[48px_repeat(6,minmax(110px,1fr))] bg-white md:min-w-0 md:grid-cols-[48px_repeat(6,minmax(0,1fr))]"
                    style={{
                        gridTemplateRows: `40px repeat(${slotCount}, ${SLOT_HEIGHT}px)`,
                    }}
                >
                    <div className="sticky left-0 z-30 border-r border-b border-gray-100 bg-gray-50" style={{ gridColumn: 1, gridRow: 1 }} />
                    {DAYS.map((day, dayIndex) => {
                        const date = dateForDay(weekStart, dayIndex);
                        const isToday = localDateString(date) === today;

                        return (
                            <div
                                key={day}
                                className={`sticky top-0 z-20 flex flex-col items-center justify-center border-r border-b px-1 text-center leading-tight ${isToday ? 'border-sky-200 bg-sky-100 text-sky-900' : 'border-gray-100 bg-gray-50 text-gray-800'}`}
                                style={{ gridColumn: dayIndex + 2, gridRow: 1 }}
                            >
                                <span className="text-xs font-semibold">{t(day, locale)}</span>
                                <span className="text-[10px]">{formatDayDate(date)}</span>
                            </div>
                        );
                    })}

                    {Array.from({ length: slotCount }, (_, slotIndex) => {
                        const minute = gridStart + slotIndex * SLOT_MINUTES;
                        const label = `${String(Math.floor(minute / 60)).padStart(2, '0')}:${String(minute % 60).padStart(2, '0')}`;

                        return (
                            <div
                                key={label}
                                className="sticky left-0 z-10 border-r border-b border-gray-100 bg-white pr-1 text-right text-[10px] text-gray-600"
                                style={{ gridColumn: 1, gridRow: slotIndex + 2 }}
                            >{slotIndex % 2 === 0 ? label : ''}</div>
                        );
                    })}

                    {DAYS.map((day, dayIndex) => {
                        const date = localDateString(dateForDay(weekStart, dayIndex));
                        const dayCourses = courses.filter((course) => course.date === date);

                        return (
                            <div
                                key={day}
                                className={`relative border-r border-gray-100 ${date === today ? 'bg-sky-50/70' : 'bg-white'}`}
                                style={{
                                    gridColumn: dayIndex + 2,
                                    gridRow: `2 / span ${slotCount}`,
                                    backgroundImage: `repeating-linear-gradient(to bottom, transparent 0, transparent ${SLOT_HEIGHT - 1}px, #f3f4f6 ${SLOT_HEIGHT - 1}px, #f3f4f6 ${SLOT_HEIGHT}px)`,
                                }}
                            >
                                {layoutDayCourses(dayCourses).map(renderCourse)}
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
