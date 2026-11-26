import { frDate, OfferCard } from '@/components/recruitment/recruitment';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import CareersLayout from '@/layouts/careers-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, MapPin } from 'lucide-react';
import { FormEvent } from 'react';

interface ShowProps {
    offer: OfferCard & { description: string; requirements: string | null; open: boolean };
    cvMaxKb: number;
    privacyContact: string | null;
}

/** A public offer and its application form. */
export default function CareersShow({ offer, cvMaxKb, privacyContact }: ShowProps) {
    const form = useForm<{ name: string; email: string; phone: string; message: string; cv: File | null; consent: boolean; website: string }>({
        name: '',
        email: '',
        phone: '',
        message: '',
        cv: null,
        consent: false,
        website: '',
    });
    const { data, setData, errors, processing, wasSuccessful } = form;
    const error = (key: keyof typeof errors) => errors[key] && <p className="text-sm text-red-700 dark:text-red-400">{errors[key]}</p>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/carrieres/${offer.slug}`, { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <CareersLayout>
            <Head title={offer.title} />
            <Link href="/carrieres" className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm">
                <ArrowLeft className="h-4 w-4 rtl:rotate-180" /> Toutes les offres
            </Link>
            <h1 className="mt-4 text-2xl font-bold">{offer.title}</h1>
            <p className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 text-sm">
                <span>{offer.contract}</span>
                {offer.department && <span>{offer.department}</span>}
                {offer.location && (
                    <span className="inline-flex items-center gap-1">
                        <MapPin className="h-3 w-3" /> {offer.location}
                    </span>
                )}
                {offer.closes_on && <span>Candidatures jusqu'au {frDate(offer.closes_on)}</span>}
            </p>

            <section className="mt-6 space-y-2">
                <h2 className="font-semibold">Le poste</h2>
                <p className="text-sm whitespace-pre-line">{offer.description}</p>
            </section>
            {offer.requirements && (
                <section className="mt-6 space-y-2">
                    <h2 className="font-semibold">Profil recherché</h2>
                    <p className="text-sm whitespace-pre-line">{offer.requirements}</p>
                </section>
            )}

            <section className="mt-10 rounded-lg border p-5">
                <h2 className="font-semibold">Postuler</h2>
                {!offer.open ? (
                    <p className="text-muted-foreground mt-2 text-sm">Cette offre est clôturée et n'accepte plus de candidatures.</p>
                ) : wasSuccessful ? (
                    <p role="status" className="mt-2 text-sm text-green-700 dark:text-green-400">
                        Merci ! Votre candidature a bien été envoyée. Nous revenons vers vous rapidement.
                    </p>
                ) : (
                    <form onSubmit={submit} className="mt-4 space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label htmlFor="apply-name">Nom et prénom</Label>
                                <Input
                                    id="apply-name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    autoComplete="name"
                                />
                                {error('name')}
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="apply-email">E-mail</Label>
                                <Input
                                    id="apply-email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                    autoComplete="email"
                                />
                                {error('email')}
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="apply-phone">Téléphone (facultatif)</Label>
                                <Input
                                    id="apply-phone"
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    autoComplete="tel"
                                />
                                {error('phone')}
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="apply-cv">CV (PDF ou Word, {Math.round(cvMaxKb / 1024)} Mo max.)</Label>
                                <Input
                                    id="apply-cv"
                                    type="file"
                                    accept=".pdf,.doc,.docx"
                                    onChange={(e) => setData('cv', e.target.files?.[0] ?? null)}
                                    required
                                />
                                {error('cv')}
                            </div>
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="apply-message">Message (facultatif)</Label>
                            <Textarea id="apply-message" rows={4} value={data.message} onChange={(e) => setData('message', e.target.value)} />
                        </div>
                        {/* Left empty by people, filled by robots. */}
                        <div className="hidden" aria-hidden="true">
                            <label htmlFor="apply-website">Site web</label>
                            <input
                                id="apply-website"
                                tabIndex={-1}
                                autoComplete="off"
                                value={data.website}
                                onChange={(e) => setData('website', e.target.value)}
                            />
                        </div>
                        <label className="flex items-start gap-2 text-sm">
                            <input type="checkbox" className="mt-1" checked={data.consent} onChange={(e) => setData('consent', e.target.checked)} />
                            <span>
                                J'accepte que mes données soient utilisées pour traiter ma candidature et conservées au plus 2 ans (loi 09-08). Je
                                peux demander leur consultation ou leur suppression{privacyContact ? ` auprès de ${privacyContact}` : ''}.
                            </span>
                        </label>
                        {error('consent')}
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Envoi…' : 'Envoyer ma candidature'}
                        </Button>
                    </form>
                )}
            </section>
        </CareersLayout>
    );
}
