import { ReactNode } from 'react';

export type NoticeFacts = {
    company: string | null;
    address: string;
    contact: string | null;
    cndpNumber: string | null;
    retention: { medical_certificates_years: number; personal_data_years: number; anonymize_after_years: number };
};

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-2">
            <h2 className="text-base font-semibold">{title}</h2>
            <div className="text-muted-foreground space-y-2 text-sm leading-relaxed">{children}</div>
        </section>
    );
}

/** The notice text, shared with the Administrateur's register page. */
export function NoticeText({ notice }: { notice: NoticeFacts }) {
    const company = notice.company ?? "l'entreprise";
    const r = notice.retention;

    return (
        <div className="space-y-6">
            <Section title="Responsable du traitement">
                <p>
                    {company}
                    {notice.address ? `, ${notice.address}` : ''}, pour la gestion de son personnel.
                    {notice.contact && (
                        <>
                            {' '}
                            Contact pour vos données : <span className="text-foreground font-medium">{notice.contact}</span>.
                        </>
                    )}
                </p>
                <p>
                    Traitement déclaré à la Commission nationale de contrôle de la protection des données à caractère personnel (CNDP)
                    {notice.cndpNumber ? (
                        <>
                            {' '}
                            sous le n° <span className="text-foreground font-medium">{notice.cndpNumber}</span>.
                        </>
                    ) : (
                        ' (numéro de déclaration à compléter par le service RH).'
                    )}
                </p>
            </Section>

            <Section title="Pourquoi vos données sont utilisées">
                <ul className="list-disc space-y-1 ps-5">
                    <li>gérer vos congés et absences (demandes, soldes, validation, calendrier d'équipe) ;</li>
                    <li>préparer la paie et les déclarations sociales (CNSS) ;</li>
                    <li>établir vos attestations de travail et de salaire ;</li>
                    <li>tenir votre dossier administratif (contrat, pièces justificatives).</li>
                </ul>
                <p>Ces traitements sont nécessaires à l'exécution de votre contrat de travail et aux obligations légales de l'employeur.</p>
            </Section>

            <Section title="Quelles données">
                <ul className="list-disc space-y-1 ps-5">
                    <li>identité et contact : nom, e-mail professionnel, téléphone et e-mail personnels, adresse, contact d'urgence ;</li>
                    <li>données administratives : matricule, CIN, n° CNSS, date et lieu de naissance, situation familiale, RIB ;</li>
                    <li>vie professionnelle : poste, département, responsable, contrats, salaire, attestations ;</li>
                    <li>congés et absences, avec leurs justificatifs ;</li>
                    <li>
                        <span className="text-foreground font-medium">données de santé</span> : uniquement les certificats médicaux des arrêts
                        maladie, consultés par le seul service RH.
                    </li>
                </ul>
                <p>CIN, n° CNSS, RIB, adresse, téléphones, e-mail personnel et salaires sont chiffrés dans la base de données.</p>
            </Section>

            <Section title="Qui y a accès">
                <ul className="list-disc space-y-1 ps-5">
                    <li>le service RH (comptes Administrateur et RH) ; la paie en lecture seule ;</li>
                    <li>votre responsable : vos demandes de congé seulement, sans justificatif ni donnée personnelle ;</li>
                    <li>vos collègues : votre nom, poste et département (organigramme) et vos dates d'absence, sans leur motif.</li>
                </ul>
                <p>Chaque consultation ou modification de vos données par une autre personne est enregistrée dans un journal.</p>
            </Section>

            <Section title="Combien de temps">
                <ul className="list-disc space-y-1 ps-5">
                    <li>certificats médicaux : {r.medical_certificates_years} ans après l'arrêt ;</li>
                    <li>données personnelles, dossier et attestations : {r.personal_data_years} ans après votre départ ;</li>
                    <li>
                        historique des congés et de la paie : conservé pour les obligations comptables, puis rendu anonyme {r.anonymize_after_years}{' '}
                        ans après votre départ.
                    </li>
                </ul>
            </Section>

            <Section title="Vos droits (loi 09-08)">
                <p>
                    Vous pouvez accéder à vos données (bouton « Télécharger mes données » ci-dessous), en demander la rectification au service RH et
                    vous opposer, pour des motifs légitimes, à un traitement qui n'est pas obligatoire. Vous pouvez aussi saisir la CNDP
                    (www.cndp.ma).
                </p>
            </Section>
        </div>
    );
}
