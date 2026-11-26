import EmptyState from '@/components/empty-state';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Download, Wallet } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mes bulletins', href: '/settings/payslips' }];
const dh = (v: number) => v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** The employee's own payslips, once the month is validated. */
export default function MyPayslips({ payslips }: { payslips: { id: number; month: string; gross: number; net: number }[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mes bulletins" />
            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Mes bulletins de paie"
                        description="Vos bulletins apparaissent ici une fois la paie du mois validée par le service RH."
                    />
                    {payslips.length === 0 ? (
                        <EmptyState
                            icon={Wallet}
                            title="Aucun bulletin pour l'instant"
                            description="Le premier apparaîtra après la validation de la paie."
                        />
                    ) : (
                        <ul className="divide-y rounded-md border">
                            {payslips.map((p) => (
                                <li key={p.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                    <div>
                                        <p className="font-medium first-letter:uppercase">
                                            {new Date(`${p.month}-01T00:00:00`).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })}
                                        </p>
                                        <p className="text-muted-foreground">
                                            Net à payer {dh(p.net)} DH · brut {dh(p.gross)} DH
                                        </p>
                                    </div>
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={`/bulletins/${p.id}/pdf`}>
                                            <Download className="mr-2 h-4 w-4" /> PDF
                                        </a>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
