import { usePage } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import StudentNavigation from '../../Components/StudentNavigation';
import StudentPagination from '../../Components/StudentPagination';
import { translate as t } from '../../i18n';

export default function Supports({ supportsCours = {} }) {
    const { locale = 'fr' } = usePage().props;
    const supports = supportsCours.data ?? [];

    return <PortalLayout title="Supports de cours">
        <StudentNavigation />
        <section className="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
            <h1 className="mb-4 text-lg font-bold text-[#692333]">{t('Supports de cours', locale)}</h1>
            <div className="divide-y">
                {supports.map((support) => <article key={support.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <h2 className="font-semibold text-slate-800">{support.titre}</h2>
                        {support.description && <p className="mt-1 whitespace-pre-line text-sm text-slate-600">{support.description}</p>}
                        <p className="mt-1 truncate text-xs text-slate-500">{support.nom_fichier}</p>
                        <time className="mt-1 block text-xs text-slate-400">{support.created_at ? new Date(support.created_at).toLocaleDateString(locale === 'fr' ? 'fr-FR' : 'en') : ''}</time>
                    </div>
                    <a href={support.download_url} className="shrink-0 rounded-lg bg-[#1a93e8] px-4 py-2 text-center text-sm font-semibold text-white">
                        {t('Télécharger le document', locale)}
                    </a>
                </article>)}
                {!supports.length && <p className="text-sm text-slate-500">{t('Aucun support de cours pour votre classe pour le moment.', locale)}</p>}
            </div>
            <StudentPagination links={supportsCours.links} />
        </section>
    </PortalLayout>;
}
