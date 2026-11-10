import { Link, usePage } from '@inertiajs/react';
import { translate as t } from '../i18n';

const links = [
    { href: '/etudiant/supports', label: 'Supports de cours' },
    { href: '/etudiant/emplois-du-temps', label: 'Emplois du temps' },
    { href: '/etudiant/infos', label: 'Fil d’informations' },
];

export default function StudentNavigation() {
    const { locale = 'fr' } = usePage().props;
    const { url } = usePage();
    const currentPath = url.split('?')[0];

    return <nav aria-label="Navigation étudiant" className="mb-6 flex flex-wrap gap-2">
        {links.map(({ href, label }) => {
            const active = currentPath === href;

            return <Link
                key={href}
                href={href}
                aria-current={active ? 'page' : undefined}
                className={`inline-flex min-h-10 items-center rounded-lg border px-4 py-2 text-sm font-semibold transition ${
                    active
                        ? 'border-[#1a93e8] bg-sky-50 text-[#1478bd]'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-sky-200 hover:bg-sky-50 hover:text-[#1478bd]'
                }`}
            >
                {t(label, locale)}
            </Link>;
        })}
    </nav>;
}
