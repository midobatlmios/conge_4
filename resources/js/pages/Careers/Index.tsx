import EmptyState from '@/components/empty-state';
import { frDate, OfferCard } from '@/components/recruitment/recruitment';
import CareersLayout from '@/layouts/careers-layout';
import { Head, Link } from '@inertiajs/react';
import { Briefcase, MapPin } from 'lucide-react';

/** Public list of open job offers. */
export default function CareersIndex({ offers }: { offers: OfferCard[] }) {
    return (
        <CareersLayout>
            <Head title="Offres d'emploi" />
            <h1 className="text-2xl font-bold">Nos offres d'emploi</h1>
            <p className="text-muted-foreground mt-1 text-sm">Rejoignez-nous : postulez en ligne en quelques minutes, avec votre CV.</p>

            {offers.length === 0 ? (
                <div className="mt-8">
                    <EmptyState
                        icon={Briefcase}
                        title="Aucune offre pour le moment"
                        description="Revenez bientôt : nos nouvelles offres sont publiées ici."
                    />
                </div>
            ) : (
                <ul className="mt-6 space-y-3">
                    {offers.map((offer) => (
                        <li key={offer.slug}>
                            <Link href={`/carrieres/${offer.slug}`} className="hover:bg-accent block rounded-lg border p-4 transition-colors">
                                <p className="font-semibold">{offer.title}</p>
                                <p className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                    <span>{offer.contract}</span>
                                    {offer.department && <span>{offer.department}</span>}
                                    {offer.location && (
                                        <span className="inline-flex items-center gap-1">
                                            <MapPin className="h-3 w-3" /> {offer.location}
                                        </span>
                                    )}
                                    {offer.closes_on && <span>Jusqu'au {frDate(offer.closes_on)}</span>}
                                </p>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </CareersLayout>
    );
}
