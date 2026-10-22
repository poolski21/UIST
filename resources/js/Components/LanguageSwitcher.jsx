import { useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { localeLabel } from '../i18n';

export default function LanguageSwitcher({ compact = false }) {
    const { locale = 'fr' } = usePage().props;
    const form = useForm({ locale });

    useEffect(() => {
        document.documentElement.lang = locale;
        form.setData('locale', locale);
    }, [locale]);

    const changeLocale = (event) => {
        const nextLocale = event.target.value;
        form.setData('locale', nextLocale);
        form.transform(() => ({ locale: nextLocale }));
        form.post('/langue', {
            preserveScroll: true,
        });
    };

    return <label className={`inline-flex items-center gap-1.5 ${compact ? 'text-xs' : 'text-sm'}`}>
        <span className="sr-only">Language / Langue</span>
        <select
            aria-label="Language / Langue"
            value={form.data.locale}
            onChange={changeLocale}
            disabled={form.processing}
            className="max-w-36 cursor-pointer rounded-md border border-current/20 bg-transparent px-2 py-1.5 font-medium text-inherit outline-none focus:ring-2 focus:ring-white/60 disabled:opacity-60"
        >
            <option value="fr" className="text-slate-900">{localeLabel('fr')}</option>
            <option value="en" className="text-slate-900">{localeLabel('en')}</option>
        </select>
        {form.errors.locale && <span role="alert" className="sr-only">{form.errors.locale}</span>}
    </label>;
}
