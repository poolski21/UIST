import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import LanguageSwitcher from '../Components/LanguageSwitcher';
import { translate as t } from '../i18n';

const features = [
    { icon: '📚', title: 'LIBRAIRIE VIRTUELLE ACCESSIBLE', text: 'L’UIST dispose d’une librairie virtuelle au service de la pédagogie et de la recherche dans tous les domaines, avec une large offre d’ebooks pour répondre à tous les besoins documentaires.', color: 'bg-sky-100' },
    { icon: '🎓', title: 'SYSTÈME LMD', text: 'Le cursus LMD (Licence, Master, Doctorat), axé sur le résultat et l’insertion socio-professionnelle des apprenants, propose un enseignement théorique et pratique de qualité.', color: 'bg-amber-100' },
    { icon: '🧑‍🏫', title: 'PÉDAGOGIE DE QUALITÉ', text: 'Un corps professoral qualifié issu des universités publiques — UFHB, Université Alassane Ouattara, INP-HB — et composé d’experts locaux.', color: 'bg-rose-100' },
    { icon: '📶', title: 'WIFI ACCESSIBLE', text: 'Le réseau WIFI est accessible aux étudiants, enseignants-chercheurs, personnels et visiteurs. Toutes les salles de cours sont couvertes.', color: 'bg-emerald-100' },
];

export default function Home({ cours = [], categories = [], socialLinks = {} }) {
    const { auth, locale = 'fr' } = usePage().props;
    const [activeSlide, setActiveSlide] = useState(0);
    const [expanded, setExpanded] = useState({});
    const [search, setSearch] = useState('');
    const filteredCourses = useMemo(
        () => cours.filter((course) => course.titre.toLocaleLowerCase('fr').includes(search.toLocaleLowerCase('fr'))),
        [cours, search],
    );
    const visibleCourses = filteredCourses.length
        ? Array.from({ length: Math.min(4, filteredCourses.length) }, (_, i) => filteredCourses[(activeSlide + i) % filteredCourses.length])
        : [];
    const toggleAll = () => {
        const expandAll = categories.some((category) => !expanded[category.id]);
        setExpanded(Object.fromEntries(categories.map((category) => [category.id, expandAll])));
    };

    return (
        <div className="min-h-screen bg-[#f7f8fa]">
            <Head title={t('Université Internationale des Sciences et Technologie', locale)} />
            <div className="bg-[#1a93e8] text-white">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-4 gap-y-1 px-3 py-2 text-center text-xs sm:justify-between sm:px-5 sm:text-sm">
                    <div className="flex flex-wrap justify-center gap-x-4 gap-y-1 sm:justify-start sm:gap-x-6"><a href="tel:+2250575948181">☎ {t('Nous appeler : 05.75.94.81.81', locale)}</a><a href="mailto:univ.uist@gmail.com">✉ {t('Courriel : univ.uist@gmail.com', locale)}</a></div>
                    <div className="flex items-center gap-3 sm:gap-5"><LanguageSwitcher compact /><Link href={auth?.user ? '/espace' : '/connexion'} className="font-semibold hover:underline">{t('Connexion', locale)}</Link></div>
                </div>
            </div>
            <header className="mx-auto flex max-w-7xl items-center gap-2 px-3 py-4 sm:gap-4 sm:px-5 sm:py-5">
                <img src="/images/uist-logo.png" alt="Université Internationale des Sciences et Technologie" className="h-auto w-32 shrink-0 sm:w-48" />
                <p className="hidden text-sm font-medium text-slate-500 md:block">Cocody Riviera · Abidjan, Côte d’Ivoire</p>
                <Link href="/espace" className="ml-auto rounded-lg bg-[#1a93e8] px-3 py-2 text-center text-xs font-semibold text-white shadow-sm hover:bg-sky-700 sm:px-5 sm:py-2.5 sm:text-sm">{t('Espace étudiant', locale)}</Link>
            </header>

            <main>
                <section className="mx-auto grid max-w-7xl gap-4 px-3 py-5 sm:gap-5 sm:px-5 sm:py-8 sm:grid-cols-2 xl:grid-cols-4">
                    {features.map((feature) => <article key={feature.title} className="rounded-2xl bg-white p-6 shadow-[0_6px_24px_rgba(15,23,42,.07)]">
                        <div className={`mb-4 flex h-14 w-14 items-center justify-center rounded-full text-2xl ${feature.color}`}>{feature.icon}</div>
                        <h2 className="mb-3 text-sm font-extrabold tracking-wide text-slate-800">{t(feature.title, locale)}</h2>
                        <p className="text-sm leading-6 text-slate-600">{t(feature.text, locale)}</p>
                    </article>)}
                </section>

                <section className="mx-auto max-w-7xl px-3 py-5 sm:px-5 sm:py-8">
                    <div className="mb-6 flex items-center justify-between">
                        <h2 className="section-title text-xl font-bold sm:text-2xl">{t('Cours disponibles', locale)}</h2>
                        <div className="flex gap-2">
                            <button aria-label={t('Cours précédents', locale)} onClick={() => setActiveSlide((activeSlide - 1 + filteredCourses.length) % Math.max(filteredCourses.length, 1))} className="h-10 w-10 rounded-full border border-slate-200 bg-white text-xl text-[#1a93e8] shadow-sm">‹</button>
                            <button aria-label={t('Cours suivants', locale)} onClick={() => setActiveSlide((activeSlide + 1) % Math.max(filteredCourses.length, 1))} className="h-10 w-10 rounded-full border border-slate-200 bg-white text-xl text-[#1a93e8] shadow-sm">›</button>
                        </div>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {visibleCourses.map((course) => <article key={course.id} className="overflow-hidden rounded-xl bg-white shadow-md">
                            <img src={course.image ? `/storage/${course.image}` : '/images/course-placeholder.svg'} alt={`Illustration du cours ${course.titre}`} className="h-40 w-full object-cover" />
                            <h3 className="min-h-14 bg-[#1a93e8] px-4 py-4 font-semibold text-white">{course.titre}</h3>
                        </article>)}
                        {!visibleCourses.length && <p className="col-span-full rounded-xl bg-white p-8 text-center text-slate-500">{t('Aucun cours ne correspond à cette recherche.', locale)}</p>}
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-3 py-5 sm:px-5 sm:py-8">
                    <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h2 className="section-title text-xl font-bold sm:text-2xl">{t('Catégories de cours', locale)}</h2>
                        <button onClick={toggleAll} className="text-sm font-semibold text-[#1a93e8] hover:underline">{t('Tout déplier / Tout replier', locale)}</button>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2">
                        {categories.map((category) => <article key={category.id} className="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm">
                            <button onClick={() => setExpanded({ ...expanded, [category.id]: !expanded[category.id] })} className="flex w-full items-center justify-between px-5 py-4 text-left font-semibold text-slate-700">
                                <span>{expanded[category.id] ? '−' : '+'}　{t(category.nom, locale)}</span>
                                <span className="rounded-full bg-[#1a93e8] px-3 py-1 text-xs font-bold text-white">{category.cours_count}</span>
                            </button>
                            {expanded[category.id] && <div className="border-t border-slate-100 px-5 py-3 text-sm text-slate-500">{category.cours?.length ? <ul className="grid gap-2 sm:grid-cols-2">{category.cours.map((course) => <li key={course.id}>• {course.titre}</li>)}</ul> : t('Aucun cours pour le moment.', locale)}</div>}
                        </article>)}
                    </div>
                </section>

                <form onSubmit={(event) => { event.preventDefault(); setActiveSlide(0); }} className="mx-auto flex max-w-3xl px-3 py-7 sm:px-5 sm:py-10">
                    <label htmlFor="course-search" className="sr-only">{t('Rechercher des cours', locale)}</label>
                    <input id="course-search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t('Rechercher des cours', locale)} className="min-w-0 flex-1 rounded-l-lg border border-slate-300 bg-white px-4 py-3 outline-none focus:border-[#1a93e8] focus:ring-2 focus:ring-sky-100" />
                    <button aria-label={t('Rechercher', locale)} className="rounded-r-lg bg-[#1a93e8] px-6 text-xl text-white">⌕</button>
                </form>
            </main>

            <footer className="bg-[#20242b] text-slate-300">
                <div className="mx-auto grid max-w-7xl gap-8 px-4 py-9 sm:grid-cols-2 sm:px-5 sm:py-12 xl:grid-cols-4">
                    <div><img src="/images/uist-logo.png" alt={t('Université Internationale des Sciences et Technologie', locale)} className="mb-5 h-auto w-48" /><p className="text-sm leading-6">{t('L’Université Internationale des Sciences et Technologie est une institution d’enseignement supérieur pluridisciplinaire. Dans son modèle pédagogique innovant, elle respecte les normes nationales et internationales. Elle est axée sur le résultat et l’insertion socio-professionnelle de ses apprenants avec un enseignement théorique et pratique de qualité. Son enseignement est basé sur le Système LMD.', locale)}</p><a href="#top" className="mt-3 inline-block text-sm text-sky-400">{t('Lire la suite »', locale)}</a></div>
                    <div><h2 className="mb-4 font-bold text-white">{t('Informations', locale)}</h2><ul className="space-y-3 text-sm"><li>{t('Bureau des inscriptions', locale)}</li><li>{t('Support aux étudiants', locale)}</li><li>{t('Lundi-Samedi de 8 h 00 à 20 h 00', locale)}</li><li><a href="mailto:info@uist.ci">info@uist.ci</a></li></ul></div>
                    <div><h2 className="mb-4 font-bold text-white">{t('Contactez-Nous', locale)}</h2><ul className="space-y-3 text-sm"><li>{t('COCODY RIVIERA, Abidjan Côte d’Ivoire', locale)}</li><li>{t('Téléphone :', locale)} <a href="tel:+2250575948181">05.75.94.81.81</a></li><li>{t('Courriel :', locale)} <a href="mailto:univ.uist@gmail.com">univ.uist@gmail.com</a></li></ul></div>
                    <div><h2 className="mb-4 font-bold text-white">{t('Suivez-Nous sur', locale)}</h2><div className="flex gap-3"><a href={socialLinks.x || '#'} aria-label="X" className="rounded-full bg-slate-700 px-3 py-2">𝕏</a><a href={socialLinks.google || '#'} aria-label="Google+" className="rounded-full bg-slate-700 px-3 py-2">G+</a><a href={socialLinks.pinterest || '#'} aria-label="Pinterest" className="rounded-full bg-slate-700 px-3 py-2">P</a><a href={socialLinks.facebook || '#'} aria-label="Facebook" className="rounded-full bg-slate-700 px-3 py-2">f</a></div></div>
                </div>
                <div className="border-t border-slate-700 py-4 text-center text-xs text-slate-500">© {new Date().getFullYear()} UIST2its · Université Internationale des Sciences et Technologie</div>
            </footer>
            <button onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })} aria-label={t('Retour en haut', locale)} className="fixed bottom-[calc(5rem+env(safe-area-inset-bottom))] right-3 flex h-10 w-10 items-center justify-center rounded-full bg-[#1a93e8] text-xl text-white shadow-lg sm:right-5 sm:h-11 sm:w-11">↑</button>
            <Link href="/connexion" aria-label={t('Aide et connexion', locale)} className="fixed bottom-[calc(1rem+env(safe-area-inset-bottom))] right-3 flex h-10 w-10 items-center justify-center rounded-full bg-[#692333] text-lg font-bold text-white shadow-lg sm:bottom-5 sm:right-5 sm:h-11 sm:w-11">?</Link>
        </div>
    );
}
