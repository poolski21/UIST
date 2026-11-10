import { Link, usePage } from '@inertiajs/react';
import PortalLayout from '../../Layouts/PortalLayout';
import StudentNavigation from '../../Components/StudentNavigation';
import StudentPagination from '../../Components/StudentPagination';
import { translate as t } from '../../i18n';

function Card({ title, children }) {
    return <section className="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 className="mb-4 text-lg font-bold text-[#692333]">{title}</h2>
        {children}
    </section>;
}

export default function Infos({ annonces = {}, notes = {}, resultats = {}, notifications = [], notificationCount = 0 }) {
    const { locale = 'fr' } = usePage().props;
    const announcements = annonces.data ?? [];
    const publishedNotes = notes.data ?? [];
    const publishedResults = resultats.data ?? [];

    return <PortalLayout title="Fil d’informations">
        <StudentNavigation />
        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p className="text-sm font-medium uppercase tracking-wider text-[#1a93e8]">{t('Espace étudiant', locale)}</p>
                <h1 className="text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Fil d’informations', locale)}</h1>
            </div>
            {notificationCount > 0 && <a href="#notifications" className="rounded-full bg-sky-100 px-4 py-2 text-sm font-semibold text-sky-800">{notificationCount} {t('notifications non lue(s)', locale)}</a>}
        </div>
        {!!notifications.length && <section id="notifications" className="mb-5 rounded-xl border border-sky-100 bg-sky-50 p-5">
            <h2 className="mb-3 font-bold text-sky-900">{t('Notifications récentes', locale)}</h2>
            <div className="grid gap-2 sm:grid-cols-2">{notifications.map((notification) => <Link key={notification.id} href={`/notifications/${notification.id}/lire`} method="post" as="button" className={`rounded-lg border bg-white p-3 text-left ${notification.read_at ? 'border-slate-100' : 'border-sky-200'}`}>
                <span className="block text-sm font-semibold text-slate-800">{notification.data.titre}</span>
                <span className="mt-1 block text-xs text-slate-500">{notification.data.message}</span>
            </Link>)}</div>
        </section>}
        <div className="space-y-5">
            <Card title={t('Fil d’informations', locale)}>
                <div className="space-y-4">
                    {announcements.map((item) => <article key={item.id} className="border-b border-slate-100 pb-4 last:border-0">
                        <div className="flex items-start justify-between gap-2">
                            <h3 className="font-semibold text-slate-800">{item.titre}</h3>
                            <time className="shrink-0 text-xs text-slate-400">{item.publiee_le ? new Date(item.publiee_le).toLocaleDateString(locale === 'fr' ? 'fr-FR' : 'en') : ''}</time>
                        </div>
                        <p className="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{item.contenu}</p>
                        {item.piece_jointe && <a href={item.piece_jointe} className="mt-2 inline-block text-sm font-semibold text-[#1a93e8]">{t('Télécharger le document', locale)}</a>}
                    </article>)}
                    {!announcements.length && <p className="text-sm text-slate-500">{t('Aucune annonce publiée pour le moment.', locale)}</p>}
                </div>
                <StudentPagination links={annonces.links} />
            </Card>
            <Card title={t('Mes notes publiées', locale)}>
                {publishedNotes.length ? <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead><tr className="border-b text-slate-500"><th className="py-2">{t('Matière', locale)}</th><th>{t('Année', locale)}</th><th className="text-right">{t('Moyenne matière', locale)}</th></tr></thead>
                        <tbody>{publishedNotes.map((note) => <tr key={note.id} className="border-b last:border-0">
                            <td className="py-3 font-medium">{note.matiere}{note.notes_td?.length > 0 && <small className="block font-normal text-slate-500">{t('TD', locale)} : {note.notes_td.map((score) => Number(score).toFixed(2)).join(' · ')} · {t('Examen', locale)} : {Number(note.note_examen).toFixed(2)}</small>}</td>
                            <td>{note.annee}</td>
                            <td className="text-right"><strong>{note.note.toFixed(2)} / 20</strong><small className={`block font-semibold ${note.validee ? 'text-emerald-700' : 'text-rose-700'}`}>{t(note.validee ? 'Matière validée' : 'Matière non validée', locale)}</small></td>
                        </tr>)}</tbody>
                    </table>
                </div> : <p className="text-sm text-slate-500">{t('Vos notes apparaîtront ici après leur publication par l’administration.', locale)}</p>}
                <StudentPagination links={notes.links} />
            </Card>
            <Card title={t('Décision de fin d’année', locale)}>
                <div className="space-y-3">
                    {publishedResults.map((resultat) => <div key={resultat.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-4">
                        <div><p className="font-semibold text-slate-800">{resultat.annee}</p><p className="text-sm text-slate-500">{t('Moyenne générale :', locale)} {resultat.moyenne.toFixed(2)} / 20 · {resultat.credits} {t('crédits', locale)}</p><p className={`mt-1 text-sm font-bold ${resultat.decision === 'admis' ? 'text-emerald-700' : 'text-rose-700'}`}>{t(resultat.decision === 'admis' ? 'ADMIS' : 'AJOURNÉ', locale)}</p></div>
                        <a href={resultat.pdf} className="w-full rounded-lg bg-[#1a93e8] px-4 py-2 text-center text-sm font-semibold text-white sm:w-auto">{t('Télécharger en PDF', locale)}</a>
                    </div>)}
                    {!publishedResults.length && <p className="text-sm text-slate-500">{t('Votre décision sera affichée après publication par l’administration.', locale)}</p>}
                </div>
                <StudentPagination links={resultats.links} />
            </Card>
        </div>
    </PortalLayout>;
}
