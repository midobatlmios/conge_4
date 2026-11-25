import { todayISO } from '@/lib/leave';
import { Demande } from '@/types';
import { router } from '@inertiajs/react';

const period = (demande: Demande) =>
    `du ${new Date(`${demande.date_debut}T00:00:00`).toLocaleDateString('fr-FR')} au ${new Date(`${demande.date_fin}T00:00:00`).toLocaleDateString('fr-FR')}`;

/** An employee may ask to cancel an accepted leave that has not started. Mirrors DemandePolicy::requestCancellation(). */
export const canRequestCancellation = (demande: Demande) => demande.etat === 'acceptée' && demande.date_debut > todayISO();

/** The employee asks to cancel their accepted leave; the days stay booked until an admin decides. */
export const requestCancellation = (demande: Demande) => {
    if (
        window.confirm(
            `Demander l'annulation de votre congé ${period(demande)} ?\n\nUn administrateur doit l'approuver ; d'ici là, le congé reste prévu.`,
        )
    ) {
        router.post(`/demandes/${demande.id}/cancellation`, {}, { preserveScroll: true });
    }
};

/** Cancels an accepted leave (approving the employee's request or not); its days are returned. */
/** Sick leave long enough for CNSS daily allowances (mirrors Demande::needsCnssFiling). */
export const needsCnssFiling = (demande: Demande, minDays: number) => demande.type_conge === 'maladie' && demande.calendar_days >= minDays;

/** HR records the day the CNSS dossier was filed (today by default), or clears it. */
export const recordCnss = (demande: Demande) => {
    const answer = window.prompt(
        demande.cnss_filed_on
            ? 'Date de dépôt du dossier CNSS (AAAA-MM-JJ). Videz le champ pour annuler le dépôt.'
            : 'Date de dépôt du dossier CNSS (AAAA-MM-JJ) :',
        demande.cnss_filed_on ?? todayISO(),
    );
    if (answer === null) return;
    router.put(`/demandes/${demande.id}/cnss`, { cnss_filed_on: answer.trim() || null }, { preserveScroll: true });
};

export const cancelLeave = (demande: Demande, requested: boolean) => {
    const question = requested
        ? `Approuver l'annulation du congé de ${demande.user.name} ${period(demande)} ?\n\nLes jours lui seront rendus.`
        : `Annuler le congé accepté de ${demande.user.name} ${period(demande)} ?\n\nLes jours lui seront rendus et il sera prévenu.`;
    if (window.confirm(question)) {
        router.put(`/demandes/${demande.id}/cancel`, {}, { preserveScroll: true });
    }
};

/** Turns down the employee's cancellation request: the leave stays accepted. */
export const keepLeave = (demande: Demande) => {
    if (window.confirm(`Refuser l'annulation et maintenir le congé de ${demande.user.name} ${period(demande)} ?`)) {
        router.put(`/demandes/${demande.id}/keep`, {}, { preserveScroll: true });
    }
};
