import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import LanguageSwitcher from '../../Components/LanguageSwitcher';
import { translate as t } from '../../i18n';

export default function Login({ studentSecondFactor = false }) {
    const { locale = 'fr' } = usePage().props;
    const [mode, setMode] = useState('student');
    const student = useForm({ matricule: '', date_naissance: '' });
    const staff = useForm({ email: '', password: '' });
    const form = mode === 'student' ? student : staff;
    const submit = (event) => {
        event.preventDefault();
        form.post(mode === 'student' ? '/connexion/etudiant' : '/connexion/personnel');
    };

    return <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-sky-50 via-white to-blue-100 px-4 py-10">
        <Head title={t('Connexion', locale)} />
        <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl sm:p-8">
            <div className="mb-6 flex items-start justify-between gap-3">
                <Link href="/" className="flex min-w-0 flex-col items-start gap-3"><img src="/images/uist-logo.png" alt={t('Université Internationale des Sciences et Technologie', locale)} className="h-auto w-44" /><span><strong className="block text-lg">UIST2its</strong><small className="text-slate-500">{t('Portail universitaire', locale)}</small></span></Link>
                <div className="shrink-0 text-slate-700"><LanguageSwitcher /></div>
            </div>
            <h1 className="mb-2 text-2xl font-bold text-slate-900">{t('Connexion à votre espace', locale)}</h1>
            <p className="mb-6 text-sm text-slate-500">{t('Choisissez votre profil pour continuer.', locale)}</p>
            <div className="mb-6 grid grid-cols-2 rounded-lg bg-slate-100 p-1">
                <button onClick={() => setMode('student')} className={`rounded-md px-3 py-2 text-sm font-semibold ${mode === 'student' ? 'bg-white text-[#1a93e8] shadow-sm' : 'text-slate-500'}`}>{t('Étudiant', locale)}</button>
                <button onClick={() => setMode('staff')} className={`rounded-md px-3 py-2 text-sm font-semibold ${mode === 'staff' ? 'bg-white text-[#1a93e8] shadow-sm' : 'text-slate-500'}`}>{t('Enseignant / Admin', locale)}</button>
            </div>
            <form onSubmit={submit} className="space-y-4">
                {mode === 'student' ? <>
                    <label className="block text-sm font-medium text-slate-700">{t('Matricule permanent', locale)}
                        <input autoComplete="username" value={student.data.matricule} onChange={(event) => student.setData('matricule', event.target.value)} required className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-[#1a93e8] focus:ring-2 focus:ring-sky-100" placeholder={t('Ex. UIST-2026-0001', locale)} />
                    </label>
                    {studentSecondFactor && <label className="block text-sm font-medium text-slate-700">{t('Date de naissance', locale)}
                        <input type="date" value={student.data.date_naissance} onChange={(event) => student.setData('date_naissance', event.target.value)} required className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5" />
                    </label>}
                    {student.errors.matricule && <p className="text-sm text-red-600">{student.errors.matricule}</p>}
                </> : <>
                    <label className="block text-sm font-medium text-slate-700">{t('Adresse e-mail', locale)}
                        <input type="email" autoComplete="username" value={staff.data.email} onChange={(event) => staff.setData('email', event.target.value)} required className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5" />
                    </label>
                    <label className="block text-sm font-medium text-slate-700">{t('Mot de passe', locale)}
                        <input type="password" autoComplete="current-password" value={staff.data.password} onChange={(event) => staff.setData('password', event.target.value)} required className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5" />
                    </label>
                    {staff.errors.email && <p className="text-sm text-red-600">{staff.errors.email}</p>}
                </>}
                <button disabled={form.processing} className="w-full rounded-lg bg-[#1a93e8] py-3 font-semibold text-white shadow-sm hover:bg-sky-700 disabled:opacity-60">{form.processing ? t('Connexion…', locale) : t('Se connecter', locale)}</button>
            </form>
            <p className="mt-6 text-center text-xs leading-5 text-slate-500">{t('Les étudiants se connectent avec leur matricule permanent. Pour toute assistance, contactez l’administration.', locale)}</p>
            <Link href="/" className="mt-4 block text-center text-sm font-medium text-[#1a93e8]">{t('← Retour à l’accueil', locale)}</Link>
        </div>
    </div>;
}
