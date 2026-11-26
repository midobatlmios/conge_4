import { toastErrors } from '@/components/app-toaster';
import { confirmAction, promptValue } from '@/components/confirm-dialog';
import { intlLocale } from '@/lib/i18n';
import { todayISO } from '@/lib/leave';
import { Demande } from '@/types';
import { router } from '@inertiajs/react';

const period = (demande: Demande) =>
    `du ${new Date(`${demande.date_debut}T00:00:00`).toLocaleDateString(intlLocale())} au ${new Date(`${demande.date_fin}T00:00:00`).toLocaleDateString(intlLocale())}`;

/** An employee may ask to cancel an accepted leave that has not started. Mirrors DemandePolicy::requestCancellation(). */
export const canRequestCancellation = (demande: Demande) => demande.etat === 'acceptée' && demande.date_debut > todayISO();

/** The employee asks to cancel their accepted leave; the days stay booked until an admin decides. */
export const requestCancellation = async (demande: Demande) => {
    const ok = await confirmAction({
        title: "Demander l'annulation de ce congé ?",
        description: `Votre congé ${period(demande)}.\nUn administrateur doit l'approuver ; d'ici là, le congé reste prévu.`,
        confirmLabel: "Demander l'annulation",
    });
    if (ok) router.post(`/demandes/${demande.id}/cancellation`, {}, { preserveScroll: true, onError: toastErrors });
};

/** Sick leave long enough for CNSS daily allowances (mirrors Demande::needsCnssFiling). */
export const needsCnssFiling = (demande: Demande, minDays: number) => demande.type_conge === 'maladie' && demande.calendar_days >= minDays;

/** HR records the day the CNSS dossier was filed (today by default), or clears it. */
export const recordCnss = async (demande: Demande) => {
    const answer = await promptValue({
        title: 'Dossier CNSS',
        description: demande.cnss_filed_on
            ? `Arrêt maladie de ${demande.user.name} ${period(demande)}.\nVidez la date pour annuler le dépôt.`
            : `Arrêt maladie de ${demande.user.name} ${period(demande)}.`,
        label: 'Date de dépôt du dossier',
        type: 'date',
        defaultValue: demande.cnss_filed_on ?? todayISO(),
        confirmLabel: 'Enregistrer',
    });
    if (answer === null) return;
    router.put(`/demandes/${demande.id}/cnss`, { cnss_filed_on: answer || null }, { preserveScroll: true, onError: toastErrors });
};

/** Cancels an accepted leave (approving the employee's request or not); its days are returned. */
export const cancelLeave = async (demande: Demande, requested: boolean) => {
    const ok = await confirmAction({
        title: requested ? "Approuver l'annulation ?" : 'Annuler ce congé accepté ?',
        description: requested
            ? `Congé de ${demande.user.name} ${period(demande)}.\nLes jours lui seront rendus.`
            : `Congé de ${demande.user.name} ${period(demande)}.\nLes jours lui seront rendus et il sera prévenu.`,
        confirmLabel: requested ? "Approuver l'annulation" : 'Annuler le congé',
        cancelLabel: 'Retour',
        destructive: true,
    });
    if (ok) router.put(`/demandes/${demande.id}/cancel`, {}, { preserveScroll: true, onError: toastErrors });
};

/** Turns down the employee's cancellation request: the leave stays accepted. */
export const keepLeave = async (demande: Demande) => {
    const ok = await confirmAction({
        title: 'Maintenir le congé ?',
        description: `L'annulation demandée par ${demande.user.name} est refusée : le congé ${period(demande)} reste prévu.`,
        confirmLabel: 'Maintenir le congé',
    });
    if (ok) router.put(`/demandes/${demande.id}/keep`, {}, { preserveScroll: true, onError: toastErrors });
};
