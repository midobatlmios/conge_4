import HalfDayFields from '@/components/demandes/HalfDayFields';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { TypeConge, endAfterWorkingDays, formatDays, leaveDays, useLeaveRules } from '@/lib/leave';
import { Demande } from '@/types';
import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface EditDemandeModalProps {
    isOpen: boolean;
    onClose: () => void;
    demande: Demande | null;
    onUpdate: () => void;
    isAdmin?: boolean;
}

type EditDemandeForm = {
    date_debut: string;
    date_fin: string;
    type_conge: TypeConge;
    etat: Demande['etat'];
    comment: string;
    start_half: boolean; // starts in the afternoon
    end_half: boolean; // ends at noon
};

const EditDemandeModal: React.FC<EditDemandeModalProps> = ({ isOpen, onClose, demande, onUpdate, isAdmin = false }) => {
    const { data, setData, put, processing, errors, clearErrors } = useForm<EditDemandeForm>({
        date_debut: '',
        date_fin: '',
        type_conge: 'annuel',
        etat: 'en attente',
        comment: '',
        start_half: false,
        end_half: false,
    });
    const [succeeded, setSucceeded] = useState(false);

    // Load the selected request into the form.
    useEffect(() => {
        if (demande) {
            setData({
                date_debut: demande.date_debut,
                date_fin: demande.date_fin,
                type_conge: demande.type_conge,
                etat: demande.etat,
                comment: demande.comment ?? '',
                start_half: demande.start_half,
                end_half: demande.end_half,
            });
            clearErrors();
            setSucceeded(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [demande]);

    const rules = useLeaveRules();
    const fixedLength = rules.typeLimits[data.type_conge];
    // Exceptional leave is taken in whole days only.
    const halfDays = rules.halfDayTypes.includes(data.type_conge);
    const nbrJours =
        data.date_debut && data.date_fin
            ? leaveDays(data.date_debut, data.date_fin, halfDays && data.start_half, halfDays && data.end_half, rules)
            : 0;

    // Fixed-length leave types (mariage, naissance, décès) get their end date computed in working days.
    const computeEndDate = (type: TypeConge, start: string, currentEnd: string) => {
        const limit = rules.typeLimits[type];
        return limit && start ? endAfterWorkingDays(start, limit, rules) : currentEnd;
    };

    // A changed date or type makes earlier date errors stale.
    const updateDates = (changes: Partial<EditDemandeForm>) => {
        setData((prev) => {
            const next = { ...prev, ...changes };
            const whole = !rules.halfDayTypes.includes(next.type_conge);
            return {
                ...next,
                date_fin: computeEndDate(next.type_conge, next.date_debut, next.date_fin),
                start_half: whole ? false : next.start_half,
                end_half: whole ? false : next.end_half,
            };
        });
        clearErrors('date_debut', 'date_fin');
    };

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        setSucceeded(false);

        put(`/demandes/${demande?.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setSucceeded(true);
                setTimeout(onUpdate, 1500);
            },
        });
    };

    const errorList = Object.values(errors);

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="flex max-h-[90vh] w-[95vw] flex-col sm:w-[85vw] md:w-[75vw] lg:w-[60vw] xl:w-[50vw]">
                <DialogHeader>
                    <DialogTitle>{t('Modifier la demande de congé')}</DialogTitle>
                    <DialogDescription>{t('Modifiez les informations de la demande de congé ci-dessous.')}</DialogDescription>
                </DialogHeader>
                <div className="-me-4 flex-1 overflow-y-auto pe-4">
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {succeeded && <div className="rounded bg-green-200 p-2 text-green-800">{t('Demande mise à jour avec succès')}</div>}
                        {errorList.length > 0 && <div className="rounded bg-red-200 p-2 text-red-800">{errorList.join(', ')}</div>}

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-1">
                                <label htmlFor="type_conge" className="text-sm font-medium">
                                    {t('Type de congé')}
                                </label>
                                <Select value={data.type_conge} onValueChange={(value: TypeConge) => updateDates({ type_conge: value })}>
                                    <SelectTrigger id="type_conge">
                                        <SelectValue placeholder={t('Sélectionner un type de congé')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="annuel">{t('Congé annuel')}</SelectItem>
                                        <SelectItem value="mariage">{t('Congé de Mariage (4 jours max)')}</SelectItem>
                                        <SelectItem value="naissance">{t('Congé de Naissance (3 jours max)')}</SelectItem>
                                        <SelectItem value="deces">{t('Congé de Décès (3 jours max)')}</SelectItem>
                                        <SelectItem value="sans_solde">{t('Congé sans solde')}</SelectItem>
                                        <SelectItem value="recuperation">{t('Récupération')}</SelectItem>
                                        <SelectItem value="maladie">{t('Congé maladie (arrêt de travail)')}</SelectItem>
                                    </SelectContent>
                                </Select>
                                {data.type_conge === 'annuel' && (
                                    <p className="text-muted-foreground text-sm">
                                        {t('Déduit des jours acquis :')} {formatDays(rules.maxDaysPerYear / 12)}{' '}
                                        {t("jour par mois travaillé (plus l'ancienneté)")}
                                    </p>
                                )}
                                {data.type_conge === 'mariage' && (
                                    <p className="text-muted-foreground text-sm">
                                        {t('4 jours dont deux jours payés et deux jours déduits automatiquement du congé')}
                                    </p>
                                )}
                                {data.type_conge === 'naissance' && (
                                    <p className="text-muted-foreground text-sm">
                                        {t('3 jours - à prendre dans un délai de un mois à compter de la date de la naissance')}
                                    </p>
                                )}
                                {data.type_conge === 'deces' && (
                                    <p className="text-muted-foreground text-sm">
                                        {t(
                                            "3 jours (décès conjoint, d'un enfant, d'un petit-enfant, d'un ascendant), dont un jour payé et deux jours déduits automatiquement du congé",
                                        )}
                                    </p>
                                )}
                                {data.type_conge === 'sans_solde' && (
                                    <p className="text-muted-foreground text-sm">{t('Ce congé sera déduit automatiquement de votre salaire')}</p>
                                )}
                                {data.type_conge === 'recuperation' && (
                                    <p className="text-muted-foreground text-sm">
                                        {t('La journée à récupérer doit se faire dans un délai de 2 mois')}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1">
                                <label htmlFor="nbr_jours" className="text-sm font-medium">
                                    {t('Nombre de jours')}
                                </label>
                                <Input id="nbr_jours" type="text" name="nbr_jours" value={formatDays(nbrJours)} className="w-full" readOnly />
                                <p className="text-muted-foreground text-xs">{t('Jours ouvrés, hors week-ends et jours fériés')}</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-1">
                                <label htmlFor="date_debut" className="text-sm font-medium">
                                    {t('Date de début')}
                                </label>
                                <Input
                                    id="date_debut"
                                    type="date"
                                    name="date_debut"
                                    value={data.date_debut}
                                    onChange={(e) => updateDates({ date_debut: e.target.value })}
                                    className={errors.date_debut ? 'border-red-500' : ''}
                                    required
                                />
                                {errors.date_debut && <p className="text-xs text-red-700 dark:text-red-400">{errors.date_debut}</p>}
                            </div>

                            <div className="space-y-1">
                                <label htmlFor="date_fin" className="text-sm font-medium">
                                    {t('Date de fin')}
                                </label>
                                <Input
                                    id="date_fin"
                                    type="date"
                                    name="date_fin"
                                    value={data.date_fin}
                                    onChange={(e) => updateDates({ date_fin: e.target.value })}
                                    className={errors.date_fin ? 'border-red-500' : ''}
                                    required
                                    readOnly={Boolean(fixedLength)}
                                />
                                {errors.date_fin && <p className="text-xs text-red-700 dark:text-red-400">{errors.date_fin}</p>}
                                {fixedLength && (
                                    <p className="text-muted-foreground text-sm">
                                        {t('La date de fin est automatiquement calculée en fonction de la date de début')}
                                    </p>
                                )}
                            </div>
                        </div>

                        {halfDays && (
                            <HalfDayFields
                                start={data.date_debut}
                                end={data.date_fin}
                                startHalf={data.start_half}
                                endHalf={data.end_half}
                                onChange={(halves) => {
                                    setData((prev) => ({ ...prev, ...halves }));
                                    clearErrors('date_debut', 'date_fin');
                                }}
                            />
                        )}

                        <div className="space-y-1">
                            <label htmlFor="comment" className="text-sm font-medium">
                                {t('Commentaire')}
                            </label>
                            <Textarea
                                id="comment"
                                name="comment"
                                value={data.comment}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => {
                                    setData('comment', e.target.value);
                                    clearErrors('comment');
                                }}
                                placeholder={t('Entrez la raison de votre demande...')}
                                className={`min-h-[100px] ${errors.comment ? 'border-red-500' : ''}`}
                            />
                            {errors.comment && <p className="text-xs text-red-700 dark:text-red-400">{errors.comment}</p>}
                        </div>

                        {/* État field: only editable by admin */}
                        {isAdmin ? (
                            <div className="space-y-1">
                                <label htmlFor="etat" className="text-sm font-medium">
                                    {t('État')}
                                </label>
                                <select
                                    id="etat"
                                    name="etat"
                                    value={data.etat}
                                    onChange={(e) => {
                                        setData('etat', e.target.value as Demande['etat']);
                                        clearErrors('etat');
                                    }}
                                    className={`w-full rounded border p-2 ${errors.etat ? 'border-red-500' : ''}`}
                                    required
                                >
                                    <option value="en attente">{t('En attente')}</option>
                                    <option value="validée manager">{t('Validée par le responsable (décision RH attendue)')}</option>
                                    <option value="acceptée">{t('Acceptée')}</option>
                                    <option value="refusée">{t('Refusée')}</option>
                                </select>
                                {errors.etat && <p className="text-xs text-red-700 dark:text-red-400">{errors.etat}</p>}
                            </div>
                        ) : (
                            <div className="space-y-1">
                                <label htmlFor="etat" className="text-sm font-medium">
                                    {t('État')}
                                </label>
                                <Input id="etat" type="text" value={data.etat} readOnly className="cursor-not-allowed bg-gray-100" />
                            </div>
                        )}
                    </form>
                </div>

                <DialogFooter className="mt-4 flex-col gap-2 sm:flex-row">
                    <Button variant="outline" type="button" onClick={onClose} disabled={processing} className="w-full sm:w-auto">
                        {t('Annuler')}
                    </Button>
                    <Button type="submit" disabled={processing} onClick={handleSubmit} className="w-full sm:w-auto">
                        {processing ? t('Mise à jour...') : t('Modifier demande')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default EditDemandeModal;
