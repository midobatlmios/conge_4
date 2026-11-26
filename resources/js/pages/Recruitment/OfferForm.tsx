import { Option, Person } from '@/components/recruitment/recruitment';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface OfferFormProps {
    offer: {
        id: number;
        title: string;
        department_id: number | null;
        location: string | null;
        contract_type: string;
        description: string;
        requirements: string | null;
        hiring_manager_id: number | null;
        closes_on: string | null;
    } | null;
    departments: Person[];
    people: Person[];
    contractTypes: Option[];
}

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

/** Create or edit a job offer (HR). */
export default function OfferForm({ offer, departments, people, contractTypes }: OfferFormProps) {
    const form = useForm({
        title: offer?.title ?? '',
        department_id: offer?.department_id ? String(offer.department_id) : '',
        location: offer?.location ?? '',
        contract_type: offer?.contract_type ?? 'cdi',
        description: offer?.description ?? '',
        requirements: offer?.requirements ?? '',
        hiring_manager_id: offer?.hiring_manager_id ? String(offer.hiring_manager_id) : '',
        closes_on: offer?.closes_on ?? '',
    });
    const { data, setData, errors, processing } = form;
    const error = (key: keyof typeof errors) => errors[key] && <p className="text-sm text-red-700 dark:text-red-400">{errors[key]}</p>;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Recrutement'), href: '/recrutement' },
        { title: offer ? offer.title : 'Nouvelle offre', href: offer ? `/recrutement/offres/${offer.id}` : '/recrutement/offres/nouvelle' },
    ];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (offer) form.put(`/recrutement/offres/${offer.id}`);
        else form.post('/recrutement/offres');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={offer ? t("Modifier l'offre") : t('Nouvelle offre')} />
            <div className="container mx-auto max-w-3xl space-y-6 px-4 py-10">
                <h1 className="text-2xl font-bold">{offer ? t("Modifier l'offre") : t("Nouvelle offre d'emploi")}</h1>
                <Card>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-1">
                                <Label htmlFor="offer-title">{t('Intitulé du poste')}</Label>
                                <Input id="offer-title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                                {error('title')}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1">
                                    <Label htmlFor="offer-contract">{t('Type de contrat')}</Label>
                                    <select
                                        id="offer-contract"
                                        className={selectClass}
                                        value={data.contract_type}
                                        onChange={(e) => setData('contract_type', e.target.value)}
                                    >
                                        {contractTypes.map((c) => (
                                            <option key={c.value} value={c.value}>
                                                {c.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="offer-department">{t('Département')}</Label>
                                    <select
                                        id="offer-department"
                                        className={selectClass}
                                        value={data.department_id}
                                        onChange={(e) => setData('department_id', e.target.value)}
                                    >
                                        <option value="">—</option>
                                        {departments.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="offer-location">{t('Lieu')}</Label>
                                    <Input
                                        id="offer-location"
                                        placeholder={t('Casablanca')}
                                        value={data.location}
                                        onChange={(e) => setData('location', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="offer-closes">{t("Candidatures jusqu'au (facultatif)")}</Label>
                                    <Input
                                        id="offer-closes"
                                        type="date"
                                        value={data.closes_on}
                                        onChange={(e) => setData('closes_on', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-1 sm:col-span-2">
                                    <Label htmlFor="offer-manager">{t('Responsable du recrutement')}</Label>
                                    <select
                                        id="offer-manager"
                                        className={selectClass}
                                        value={data.hiring_manager_id}
                                        onChange={(e) => setData('hiring_manager_id', e.target.value)}
                                    >
                                        <option value="">{t('— (RH seulement)')}</option>
                                        {people.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="text-muted-foreground text-xs">
                                        {t('Il voit les candidats de cette offre, ouvre les CV et évalue les entretiens.')}
                                    </p>
                                </div>
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="offer-description">{t('Description du poste')}</Label>
                                <Textarea
                                    id="offer-description"
                                    rows={8}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    required
                                />
                                {error('description')}
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="offer-requirements">{t('Profil recherché (facultatif)')}</Label>
                                <Textarea
                                    id="offer-requirements"
                                    rows={5}
                                    value={data.requirements}
                                    onChange={(e) => setData('requirements', e.target.value)}
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" asChild>
                                    <Link href={offer ? `/recrutement/offres/${offer.id}` : '/recrutement'}>{t('Annuler')}</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {t('Enregistrer')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
