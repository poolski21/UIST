import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import LanguageSwitcher from '../Components/LanguageSwitcher';
import { translate as t } from '../i18n';

export default function PortalLayout({ title, children }) {
    const { auth, flash, locale = 'fr' } = usePage().props;
    const logout = useForm();
    const [menuOpen, setMenuOpen] = useState(false);
    const user = auth?.user;
    const links = user?.role === 'admin'
        ? [['/admin', 'Tableau de bord'], ['/admin/gestion', 'Gestion académique'], ['/admin/annonces', 'Annonces'], ['/admin/emplois-du-temps', 'Emplois du temps'], ['/admin/notes', 'Notes'], ['/admin/resultats', 'Décisions'], ['/admin/securite/mots-de-passe', 'Mots de passe'], ['/admin/listes-classe', 'Listes de classe']]
        : user?.role === 'teacher'
            ? [['/enseignant/notes', 'Saisie des notes']]
            : [['/etudiant', 'Mon espace']];

    return <div className="min-h-screen bg-slate-50">
        <Head title={t(title, locale)} />
        <header className="border-b border-slate-200 bg-white">
            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-3 py-3 sm:gap-4 sm:px-5 sm:py-4">
                <Link href="/" className="flex min-w-0 items-center gap-2 sm:gap-3"><img src="/images/uist-logo.png" alt="Université Internationale des Sciences et Technologie" className="h-auto w-28 sm:w-36" /><span className="shrink-0 text-sm font-bold text-slate-800 sm:text-base">UIST2its</span></Link>
                <button
                    type="button"
                    aria-label={menuOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
                    aria-expanded={menuOpen}
                    aria-controls="portal-navigation"
                    onClick={() => setMenuOpen(!menuOpen)}
                    className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-xl text-slate-700 hover:bg-slate-50 md:hidden"
                >{menuOpen ? '×' : '☰'}</button>
                <nav id="portal-navigation" className={`${menuOpen ? 'flex' : 'hidden'} w-full flex-col items-stretch gap-1 border-t border-slate-100 pt-3 text-sm md:flex md:w-auto md:flex-row md:items-center md:gap-x-5 md:border-0 md:pt-0`}>
                    {links.map(([href, label]) => <Link key={href} href={href} onClick={() => setMenuOpen(false)} className="rounded-md px-3 py-2 font-medium text-slate-600 hover:bg-sky-50 hover:text-[#1a93e8] md:px-0 md:py-1 md:hover:bg-transparent">{t(label, locale)}</Link>)}
                    <div className="px-3 py-2 text-slate-700 md:px-0 md:py-1"><LanguageSwitcher /></div>
                    <span className="px-3 py-2 text-slate-400 md:px-0 md:py-1">{user?.name}</span>
                    <button onClick={() => logout.post('/deconnexion')} className="rounded-md px-3 py-2 text-left font-semibold text-rose-600 hover:bg-rose-50 md:px-0 md:py-1 md:hover:bg-transparent">{t('Déconnexion', locale)}</button>
                </nav>
            </div>
        </header>
        <main className="mx-auto max-w-7xl px-3 py-5 sm:px-5 sm:py-8">
            {flash?.success && <div role="status" className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</div>}
            {children}
        </main>
        <footer className="border-t border-slate-200 bg-white px-5 py-5 text-center text-xs text-slate-500">{t('Université Internationale des Sciences et Technologie', locale)} · Cocody Riviera, Abidjan</footer>
    </div>;
}
