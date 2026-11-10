import { Link, usePage } from '@inertiajs/react';
import { translate as t } from '../i18n';

function labelForPaginationLink(label, index, links, locale) {
    if (index === 0) {
        return t('Précédent', locale);
    }

    if (index === links.length - 1) {
        return t('Suivant', locale);
    }

    return label;
}

export default function StudentPagination({ links = [] }) {
    const { locale = 'fr' } = usePage().props;

    if (links.length <= 3) {
        return null;
    }

    return <nav aria-label={t('Pagination', locale)} className="mt-5 flex flex-wrap items-center justify-center gap-2">
        {links.map((link, index) => {
            const label = labelForPaginationLink(link.label, index, links, locale);
            const className = `inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-medium ${
                link.active
                    ? 'border-[#1a93e8] bg-[#1a93e8] text-white'
                    : 'border-slate-200 bg-white text-slate-600'
            } ${link.url ? 'hover:bg-sky-50' : 'cursor-not-allowed opacity-50'}`;

            return link.url
                ? <Link key={`${link.label}-${index}`} href={link.url} preserveScroll aria-current={link.active ? 'page' : undefined} className={className}>{label}</Link>
                : <span key={`${link.label}-${index}`} aria-disabled="true" className={className}>{label}</span>;
        })}
    </nav>;
}
