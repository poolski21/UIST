import { Link, usePage } from '@inertiajs/react';
import { BookOpen, CalendarDays, Megaphone } from 'lucide-react';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

const destinations = [
    { href: '/etudiant/supports', title: 'Supports de cours', description: 'Consultez et téléchargez les documents de vos cours.', Icon: BookOpen },
    { href: '/etudiant/emplois-du-temps', title: 'Emplois du temps', description: 'Retrouvez le programme de votre semaine.', Icon: CalendarDays },
    { href: '/etudiant/infos', title: 'Fil d’informations', description: 'Consultez les annonces, vos notes et vos résultats.', Icon: Megaphone },
];

export default function StudentDashboard() {
    const { locale = 'fr' } = usePage().props;

    return <PortalLayout title="Mon espace étudiant">
        <div className="mb-7">
            <p className="text-sm font-medium uppercase tracking-wider text-[#1a93e8]">{t('Espace étudiant', locale)}</p>
            <h1 className="text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Bienvenue sur votre portail', locale)}</h1>
            <p className="mt-1 text-sm text-slate-500 sm:text-base">{t('Choisissez une rubrique pour continuer.', locale)}</p>
        </div>
        <div className="grid gap-4 md:grid-cols-3">
            {destinations.map(({ href, title, description, Icon }) => <Link
                key={href}
                href={href}
                className="group flex min-h-40 flex-col rounded-xl border border-slate-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-md"
            >
                <Icon aria-hidden="true" className="mb-4 h-7 w-7 text-[#1a93e8]" />
                <h2 className="font-bold text-[#692333]">{t(title, locale)}</h2>
                <p className="mt-2 text-sm leading-6 text-slate-600">{t(description, locale)}</p>
            </Link>)}
        </div>
    </PortalLayout>;
}
