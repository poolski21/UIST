import { usePage } from '@inertiajs/react';
import WeeklyScheduleGrid from '../../Components/WeeklyScheduleGrid';
import StudentNavigation from '../../Components/StudentNavigation';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

export default function Emplois({ emploiDuTemps = [], weekStart = '' }) {
    const { locale = 'fr' } = usePage().props;

    return <PortalLayout title="Emplois du temps">
        <StudentNavigation />
        <section className="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
            <h1 className="mb-4 text-lg font-bold text-[#692333]">{t('Emplois du temps', locale)}</h1>
            <WeeklyScheduleGrid courses={emploiDuTemps} weekStart={weekStart} />
        </section>
    </PortalLayout>;
}
