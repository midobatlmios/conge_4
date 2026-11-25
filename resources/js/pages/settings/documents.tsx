import DocumentFolder, { FolderDocument } from '@/components/employees/DocumentFolder';
import HeadingSmall from '@/components/heading-small';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mes documents', href: '/settings/documents' }];

/** The employee's own document folder, read-only: the HR department keeps it up to date. */
export default function MyDocuments({ documents }: { documents: FolderDocument[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mes documents" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Mes documents"
                        description="Votre dossier administratif (contrat, copie CIN, diplômes…), tenu à jour par le service RH."
                    />
                    <DocumentFolder documents={documents} />
                    <p className="text-muted-foreground text-sm">Un document manque ou n'est plus à jour ? Transmettez-le au service RH.</p>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
