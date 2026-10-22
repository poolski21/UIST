import { Link, usePage } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

const destinations = [
    ['/admin/gestion', 'Gérer les étudiants, enseignants, filières, niveaux, matières et cours', 'Gestion académique'],
    ['/admin/annonces', 'Informer tous les étudiants ou cibler une formation', 'Publier une annonce'],
    ['/admin/emplois-du-temps', 'Planifier et publier le programme de la semaine', 'Emplois du temps'],
    ['/admin/notes', 'Consulter et publier les notes saisies', 'Notes d’examen'],
    ['/admin/resultats', 'Calculer, décider et publier les résultats annuels', 'Validation de l’année'],
];

export default function Dashboard({ stats }) {
    const { locale = 'fr' } = usePage().props;
    return <PortalLayout title="Administration">
        <div className="mb-8"><p className="text-sm font-semibold uppercase tracking-wider text-[#1a93e8]">{t('Administration', locale)}</p><h1 className="text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Tableau de bord', locale)}</h1><p className="mt-1 text-sm text-slate-500 sm:text-base">{t('Pilotage des activités académiques de l’UIST.', locale)}</p></div>
        <div className="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{Object.entries(stats).map(([key, value]) => <div key={key} className="rounded-xl bg-white p-5 shadow-sm"><p className="text-sm capitalize text-slate-500">{t(key, locale)}</p><p className="mt-2 text-3xl font-bold text-slate-900">{value}</p></div>)}</div>
        <div className="grid gap-4 md:grid-cols-2">
            {destinations.map(([href, description, title]) => <Link href={href} key={href} className="group rounded-xl border border-slate-100 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"><span className="text-lg font-bold text-[#692333] group-hover:text-[#1a93e8]">{t(title, locale)} →</span><p className="mt-2 text-sm text-slate-600">{t(description, locale)}</p></Link>)}
        </div>
    </PortalLayout>;
}
