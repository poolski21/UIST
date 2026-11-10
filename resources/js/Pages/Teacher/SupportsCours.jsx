import { useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import PortalLayout from '../../Layouts/PortalLayout';
import { translate as t } from '../../i18n';

export default function SupportsCours({ filieres = [], supports = [] }) {
    const { locale = 'fr' } = usePage().props;
    const fileInput = useRef(null);
    const form = useForm({
        titre: '',
        description: '',
        fichier: null,
        niveaux_ids: [],
    });

    const submit = (event) => {
        event.preventDefault();
        form.post('/enseignant/supports-cours', {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    const toggleLevel = (niveauId, checked) => {
        const selected = form.data.niveaux_ids;
        form.setData(
            'niveaux_ids',
            checked
                ? [...selected, niveauId]
                : selected.filter((id) => String(id) !== String(niveauId)),
        );
    };

    return <PortalLayout title="Supports de cours">
        <h1 className="mb-2 text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">{t('Supports de cours', locale)}</h1>
        <p className="mb-7 text-sm text-slate-500 sm:text-base">{t('Envoyez un document PDF ou Word à une ou plusieurs classes.', locale)}</p>

        <form onSubmit={submit} className="mb-8 grid gap-5 rounded-xl bg-white p-5 shadow-sm sm:p-6">
            <label className="block text-sm font-medium text-slate-700">
                {t('Titre du cours', locale)}
                <input
                    required
                    maxLength="255"
                    value={form.data.titre}
                    onChange={(event) => form.setData('titre', event.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"
                />
                {form.errors.titre && <span role="alert" className="mt-1 block text-sm text-red-600">{form.errors.titre}</span>}
            </label>

            <label className="block text-sm font-medium text-slate-700">
                {t('Description (facultative)', locale)}
                <textarea
                    rows="3"
                    maxLength="5000"
                    value={form.data.description}
                    onChange={(event) => form.setData('description', event.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"
                />
                {form.errors.description && <span role="alert" className="mt-1 block text-sm text-red-600">{form.errors.description}</span>}
            </label>

            <label className="block text-sm font-medium text-slate-700">
                {t('Document PDF ou Word (300 Mo maximum)', locale)}
                <input
                    ref={fileInput}
                    type="file"
                    required
                    accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    onChange={(event) => form.setData('fichier', event.target.files?.[0] ?? null)}
                    className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
                {form.errors.fichier && <span role="alert" className="mt-1 block text-sm text-red-600">{form.errors.fichier}</span>}
            </label>

            <fieldset className="space-y-4 rounded-lg border border-slate-200 p-4">
                <legend className="px-1 text-sm font-semibold text-slate-800">{t('Classes destinataires', locale)}</legend>
                <p className="text-sm text-slate-500">{t('Sélectionnez une ou plusieurs filières et niveaux. Le document sera disponible immédiatement.', locale)}</p>
                {form.errors.niveaux_ids && <p role="alert" className="text-sm text-red-600">{form.errors.niveaux_ids}</p>}
                {filieres.map((filiere) => <fieldset key={filiere.id} className="rounded-lg bg-slate-50 p-3">
                    <legend className="px-1 text-sm font-semibold text-[#692333]">{filiere.nom}</legend>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {filiere.niveaux.map((niveau) => <label key={niveau.id} className="flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={form.data.niveaux_ids.some((id) => String(id) === String(niveau.id))}
                                onChange={(event) => toggleLevel(niveau.id, event.target.checked)}
                                className="rounded border-slate-300 text-[#1a93e8] focus:ring-[#1a93e8]"
                            />
                            {niveau.nom}
                        </label>)}
                    </div>
                </fieldset>)}
                {form.errors['niveaux_ids.0'] && <p role="alert" className="text-sm text-red-600">{form.errors['niveaux_ids.0']}</p>}
            </fieldset>

            {form.progress && <progress className="w-full" value={form.progress.percentage} max="100">{form.progress.percentage}%</progress>}
            <button type="submit" disabled={form.processing} className="min-h-11 justify-self-start rounded-lg bg-[#1a93e8] px-5 py-2.5 font-semibold text-white hover:bg-sky-700 disabled:opacity-60">
                {form.processing ? t('Envoi en cours…', locale) : t('Envoyer aux classes sélectionnées', locale)}
            </button>
        </form>

        <section className="overflow-hidden rounded-xl bg-white shadow-sm">
            <div className="border-b px-5 py-4 sm:px-6"><h2 className="font-bold text-[#692333]">{t('Supports envoyés', locale)}</h2></div>
            <div className="divide-y">
                {supports.map((support) => <article key={support.id} className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div className="min-w-0">
                        <h3 className="font-semibold text-slate-800">{support.titre}</h3>
                        {support.description && <p className="mt-1 whitespace-pre-line text-sm text-slate-600">{support.description}</p>}
                        <p className="mt-1 text-xs text-slate-500">{support.niveaux.map((niveau) => `${niveau.filiere} · ${niveau.nom}`).join(' / ')}</p>
                        <p className="mt-1 truncate text-xs text-slate-400">{support.nom_fichier}</p>
                    </div>
                    <a href={support.download_url} className="shrink-0 rounded-lg border border-[#1a93e8] px-4 py-2 text-center text-sm font-semibold text-[#1a93e8]">
                        {t('Télécharger le document', locale)}
                    </a>
                </article>)}
                {!supports.length && <p className="p-5 text-sm text-slate-500">{t('Vous n’avez pas encore envoyé de support.', locale)}</p>}
            </div>
        </section>
    </PortalLayout>;
}
