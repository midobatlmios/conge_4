import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { CSSProperties, FormEvent, useRef } from 'react';

type CompanyFields = {
    name: string;
    legal_form: string;
    address: string;
    city: string;
    phone: string;
    email: string;
    ice: string;
    rc: string;
    if_number: string;
    cnss_number: string;
    signatory_name: string;
    signatory_title: string;
    /** Accent color, #RRGGBB (empty: the default red). */
    brand_color: string;
};

const FIELDS: { field: Exclude<keyof CompanyFields, 'brand_color'>; label: string; placeholder?: string; required?: boolean }[] = [
    { field: 'name', label: 'Raison sociale', required: true },
    { field: 'legal_form', label: 'Forme juridique', placeholder: 'SARL, SA…' },
    { field: 'address', label: 'Adresse', required: true },
    { field: 'city', label: 'Ville', required: true },
    { field: 'phone', label: 'Téléphone' },
    { field: 'email', label: 'E-mail' },
    { field: 'ice', label: 'ICE', placeholder: '15 chiffres' },
    { field: 'rc', label: 'Registre de commerce (RC)' },
    { field: 'if_number', label: 'Identifiant fiscal (IF)' },
    { field: 'cnss_number', label: "N° d'affiliation CNSS" },
    { field: 'signatory_name', label: 'Signataire des attestations', placeholder: 'Nom et prénom', required: true },
    { field: 'signatory_title', label: 'Fonction du signataire', placeholder: 'Directeur des ressources humaines' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: '/dashboard' },
    { title: 'Entreprise', href: '/admin/company' },
];

interface CompanyProps {
    company: Record<keyof CompanyFields, string | null>;
    /** Logo URL, or null. */
    logo: string | null;
    defaultColor: string;
    missing: string[];
}

/** Company details printed on attestations, and its branding: logo, accent color. */
export default function Company({ company, logo, defaultColor, missing }: CompanyProps) {
    const form = useForm<CompanyFields>({
        ...(Object.fromEntries(FIELDS.map(({ field }) => [field, company[field] ?? ''])) as Omit<CompanyFields, 'brand_color'>),
        brand_color: company.brand_color ?? '',
    });
    const color = form.data.brand_color || defaultColor;
    const initials = form.data.name
        .split(/[\s'’-]+/)
        .filter((word) => word && !['de', 'du', 'des', 'la', 'le', 'les', 'et', 'l', 'd', 'sarl', 'sa', 'sas', 'sarlau'].includes(word.toLowerCase()))
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('');
    const logoForm = useForm<{ logo: File | null }>({ logo: null });
    const logoInput = useRef<HTMLInputElement>(null);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/admin/company', { preserveScroll: true });
    };

    const uploadLogo = (file: File | null) => {
        if (!file) return;
        logoForm.setData('logo', file);
        router.post(
            '/admin/company/logo',
            { logo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) => logoForm.setError('logo', errors.logo),
                onFinish: () => {
                    if (logoInput.current) logoInput.current.value = '';
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Entreprise" />
            <div className="container mx-auto max-w-3xl space-y-6 px-4 py-10">
                <div>
                    <h1 className="text-2xl font-bold">Entreprise</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Ces informations figurent sur les attestations ; le nom, le logo et la couleur s'affichent aussi dans le menu et sur la page
                        de connexion.
                    </p>
                </div>

                {missing.length > 0 && (
                    <div
                        role="status"
                        className="flex items-start gap-3 rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300"
                    >
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <p>À compléter avant de délivrer des attestations : {missing.join(', ')}.</p>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Informations légales</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                {FIELDS.map(({ field, label, placeholder, required }) => (
                                    <div key={field} className={`space-y-1 ${field === 'address' ? 'sm:col-span-2' : ''}`}>
                                        <Label htmlFor={field}>
                                            {label}
                                            {required ? ' *' : ''}
                                        </Label>
                                        <Input
                                            id={field}
                                            value={form.data[field]}
                                            placeholder={placeholder}
                                            onChange={(e) => form.setData(field, e.target.value)}
                                            required={required}
                                        />
                                        <InputError message={form.errors[field]} />
                                    </div>
                                ))}
                                <div className="space-y-1">
                                    <Label htmlFor="brand_color">Couleur principale</Label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            id="brand_color"
                                            type="color"
                                            value={color}
                                            onChange={(e) => form.setData('brand_color', e.target.value)}
                                            className="h-9 w-14 cursor-pointer rounded border bg-transparent p-1"
                                        />
                                        <span className="text-muted-foreground font-mono text-sm">{color}</span>
                                        {form.data.brand_color && form.data.brand_color.toLowerCase() !== defaultColor.toLowerCase() && (
                                            <Button type="button" variant="ghost" size="sm" onClick={() => form.setData('brand_color', '')}>
                                                Couleur par défaut
                                            </Button>
                                        )}
                                    </div>
                                    <InputError message={form.errors.brand_color} />
                                </div>
                                <div className="space-y-1">
                                    <span className="text-sm font-medium">Aperçu du menu</span>
                                    <div className="flex items-center gap-3 rounded-md border bg-white p-2 dark:bg-[#181818]">
                                        {logo ? (
                                            <img src={logo} alt="" className="h-9 max-w-24 object-contain" />
                                        ) : (
                                            <span
                                                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-white"
                                                style={{ backgroundColor: color }}
                                            >
                                                {initials}
                                            </span>
                                        )}
                                        <span className="min-w-0 leading-tight">
                                            <span
                                                className="block truncate text-sm font-extrabold text-(--brand) dark:text-white"
                                                style={{ '--brand': color } as CSSProperties}
                                            >
                                                {form.data.name || 'Votre entreprise'}
                                            </span>
                                            <span className="text-muted-foreground text-xs uppercase">Congés & RH</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-center justify-end gap-3">
                                {form.recentlySuccessful && <span className="text-sm text-green-700 dark:text-green-400">Enregistré.</span>}
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? 'Enregistrement…' : 'Enregistrer'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Logo</CardTitle>
                        <CardDescription>
                            PNG ou JPG, 1 Mo maximum. Affiché dans le menu, sur la page de connexion et en haut des attestations. Sans logo, les
                            initiales de l'entreprise apparaissent dans la couleur principale.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center">
                        {logo ? (
                            // The URL changes after each upload, so the browser shows the new logo.
                            <img src={logo} alt="Logo de l'entreprise" className="h-16 max-w-48 rounded border bg-white object-contain p-1" />
                        ) : (
                            <div className="text-muted-foreground flex h-16 w-32 items-center justify-center rounded border border-dashed text-xs">
                                Aucun logo
                            </div>
                        )}
                        <div className="space-y-1">
                            <Input
                                ref={logoInput}
                                type="file"
                                accept=".png,.jpg,.jpeg"
                                aria-label="Logo"
                                onChange={(e) => uploadLogo(e.target.files?.[0] ?? null)}
                            />
                            <InputError message={logoForm.errors.logo} />
                            {logo && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="text-red-600"
                                    onClick={() => router.delete('/admin/company/logo', { preserveScroll: true })}
                                >
                                    Supprimer le logo
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
