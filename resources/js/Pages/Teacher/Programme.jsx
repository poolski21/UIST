import { usePage } from '@inertiajs/react';
import WeeklyScheduleGrid from '../../Components/WeeklyScheduleGrid';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

export default function Programme({ emploiDuTemps = [], weekStart = '' }) {
    const { locale = 'fr' } = usePage().props;

    return (
        <PortalLayout title="Mon programme">
            <div className="mb-6">
                <p className="text-sm font-medium uppercase tracking-wider text-[#1a93e8]">{t('Programme de la semaine', locale)}</p>
                <h1 className="text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Mon programme', locale)}</h1>
            </div>
            <section className="rounded-xl border border-slate-100 bg-white p-3 shadow-sm sm:p-5">
                <WeeklyScheduleGrid courses={emploiDuTemps} weekStart={weekStart} isTeacher />
            </section>
        </PortalLayout>
    );
}
