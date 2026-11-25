import HalfDayFields from '@/components/demandes/HalfDayFields';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    TypeConge,
    allowanceDays,
    availableBy,
    endAfterWorkingDays,
    formatDays,
    leaveDays,
    nextWorkingDay,
    parseISODate,
    toISODate,
    todayISO,
    useLeaveRules,
} from '@/lib/leave';
import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type AddDemandeForm = {
    user_id: string;
    date_debut: string;
    date_fin: string;
    type_conge: TypeConge;
    comment: string;
    start_half: boolean; // starts in the afternoon
    end_half: boolean; // ends at noon
    document: File | null; // optional certificate (acte de mariage, de naissance…)
};

export const AddDemandeModal = ({
    isOpen,
    onClose,
    isAdmin = false,
    users = [],
}: {
    isOpen: boolean;
    onClose: () => void;
    isAdmin?: boolean;
    users?: { id: number; name: string }[];
}) => {
    const rules = useLeaveRules();
    const form = useForm<AddDemandeForm>({
        user_id: '',
        date_debut: '',
        date_fin: '',
        type_conge: 'annuel',
        comment: '',
        start_half: false,
        end_half: false,
        document: null,
    });
    const { data, setData, errors, processing } = form;
    const [succeeded, setSucceeded] = useState(false);

    // Fixed-length leave types (mariage, naissance, décès) get their end date computed.
    const fixedLength = rules.typeLimits[data.type_conge];
    // Exceptional leave is taken in whole days only.
    const halfDays = rules.halfDayTypes.includes(data.type_conge);
    const nbr_jours =
        data.date_debut && data.date_fin
            ? leaveDays(data.date_debut, data.date_fin, halfDays && data.start_half, halfDays && data.end_half, rules)
            : 0;

    // Warn (without blocking) when an employee asks for days they won't have earned by the start date,
    // or for annual leave during their first 6 months. The admin still can't accept it; admins filing
    // for someone else don't see their own balance here.
    const balance = isAdmin ? null : rules.balance;
    const requestedFromAllowance = allowanceDays(data.type_conge, nbr_jours, rules);
    const availableAtStart =
        balance && data.date_debut.startsWith(String(new Date().getFullYear())) ? availableBy(data.date_debut, balance) - balance.usedDays : null;
    const exceedsBalance = availableAtStart !== null && requestedFromAllowance > availableAtStart;
    const beforeEligibility =
        balance?.eligibleFrom != null &&
        rules.countedTypes.includes(data.type_conge) &&
        data.date_debut !== '' &&
        data.date_debut < balance.eligibleFrom;

    // A changed date or type makes earlier date errors stale.
    useEffect(() => {
        form.clearErrors('date_debut', 'date_fin');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.date_debut, data.date_fin, data.type_conge]);

    // Reset the dates when the leave type changes; fixed-length types start on the next working day.
    useEffect(() => {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const start = fixedLength ? toISODate(nextWorkingDay(tomorrow, rules)) : '';
        setData((prev) => ({
            ...prev,
            comment: prev.type_conge === 'recuperation' ? 'Veuillez préciser les dates à récupérer' : '',
            date_debut: start,
            date_fin: fixedLength ? endAfterWorkingDays(start, fixedLength, rules) : '',
            start_half: false,
            end_half: false,
            document: null,
        }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.type_conge]);

    // Keep the end date of fixed-length leave in sync with the start date.
    useEffect(() => {
        if (fixedLength && data.date_debut) {
            setData('date_fin', endAfterWorkingDays(data.date_debut, fixedLength, rules));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.date_debut, fixedLength, rules]);

    // Employees can't start leave in the past, except a sick leave declared within 48 hours.
    const earliestStart = (() => {
        if (data.type_conge !== 'maladie') return todayISO();
        const date = new Date();
        date.setDate(date.getDate() - rules.sickLeaveBackdateDays);
        return toISODate(date);
    })();

    // Quick checks before the round trip; the server repeats all of them.
    const clientErrors = () => {
        const found: Partial<Record<keyof AddDemandeForm, string>> = {};

        if (isAdmin && !data.user_id) {
            found.user_id = 'Veuillez sélectionner un employé';
        }
        if (!data.date_debut) {
            found.date_debut = 'La date de début est obligatoire';
        } else if (!isAdmin && data.date_debut < earliestStart) {
            found.date_debut =
                data.type_conge === 'maladie'
                    ? 'Un arrêt maladie se déclare dans les 48 heures. Pour un arrêt plus ancien, adressez-vous au service RH.'
                    : 'La date de début ne peut pas être dans le passé';
        }
        if (!data.date_fin) {
            found.date_fin = 'La date de fin est obligatoire';
        } else if (data.date_debut && data.date_fin < data.date_debut) {
            found.date_fin = 'La date de fin doit être après la date de début';
        } else if (data.date_debut && nbr_jours === 0) {
            found.date_fin = 'La période choisie ne contient aucun jour ouvré';
        } else if (fixedLength && nbr_jours > fixedLength) {
            found.date_fin = `Ce type de congé ne peut pas dépasser ${fixedLength} jours ouvrés`;
        }
        if (data.type_conge === 'recuperation' && !data.comment) {
            found.comment = 'Veuillez renseigner les dates sujet de récupération';
        }

        return found;
    };

    const close = () => {
        form.reset();
        form.clearErrors();
        setSucceeded(false);
        onClose();
    };

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        setSucceeded(false);
        form.clearErrors();

        const found = clientErrors();
        if (Object.keys(found).length > 0) {
            form.setError(found as Record<keyof AddDemandeForm, string>);
            return;
        }

        // The server sets the request date, status, owner (unless an admin picks one) and day count.
        form.transform(({ user_id, start_half, end_half, ...rest }) => ({
            ...rest,
            start_half: halfDays && start_half,
            end_half: halfDays && end_half,
            ...(isAdmin ? { user_id } : {}),
        }));
        form.post('/demandes', {
            forceFormData: true, // may carry a file
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setSucceeded(true);
                setTimeout(close, 1500);
            },
        });
    };

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <Dialog open={isOpen} onOpenChange={close}>
            <DialogContent className="flex max-h-[90vh] w-[95vw] flex-col sm:w-[85vw] md:w-[75vw] lg:w-[60vw] xl:w-[50vw]">
                <DialogHeader>
                    <DialogTitle>Ajouter une demande de congé</DialogTitle>
                    <DialogDescription>Remplissez le formulaire ci-dessous pour soumettre une nouvelle demande de congé.</DialogDescription>
                </DialogHeader>
                <div className="-mr-4 flex-1 overflow-y-auto pr-4">
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {succeeded && <div className="rounded bg-green-200 p-2 text-green-800">Demande ajoutée avec succès</div>}
                        {hasErrors && <div className="rounded bg-red-200 p-2 text-red-800">Veuillez corriger les erreurs dans le formulaire</div>}

                        {isAdmin && (
                            <div className="space-y-1">
                                <label htmlFor="user_id" className="text-sm font-medium">
                                    Employé
                                </label>
                                <Select value={data.user_id} onValueChange={(value) => setData('user_id', value)}>
                                    <SelectTrigger className={errors.user_id ? 'border-red-500' : ''}>
                                        <SelectValue placeholder="Sélectionner un employé" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {users.map((user) => (
                                            <SelectItem key={user.id} value={user.id.toString()}>
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.user_id && <p className="text-xs text-red-500">{errors.user_id}</p>}
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-1">
                                <label htmlFor="type_conge" className="text-sm font-medium">
                                    Type de congé
                                </label>
                                <Select value={data.type_conge} onValueChange={(value: TypeConge) => setData('type_conge', value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sélectionner un type de congé" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="annuel">Congé annuel</SelectItem>
                                        <SelectItem value="mariage">Congé de Mariage (4 jours max)</SelectItem>
                                        <SelectItem value="naissance">Congé de Naissance (3 jours)</SelectItem>
                                        <SelectItem value="deces">Congé de Décès (3 jours max)</SelectItem>
                                        <SelectItem value="sans_solde">Congé sans solde</SelectItem>
                                        <SelectItem value="recuperation">Récupération</SelectItem>
                                        <SelectItem value="maladie">Congé maladie (arrêt de travail)</SelectItem>
                                    </SelectContent>
                                </Select>
                                {data.type_conge === 'annuel' && (
                                    <p className="text-sm text-gray-500">
                                        Déduit des jours acquis : {formatDays(rules.maxDaysPerYear / 12)} jour par mois travaillé (plus l'ancienneté)
                                    </p>
                                )}
                                {data.type_conge === 'mariage' && (
                                    <p className="text-sm text-gray-500">
                                        4 jours dont deux jours payés et deux jours déduits automatiquement du congé
                                    </p>
                                )}
                                {data.type_conge === 'naissance' && (
                                    <p className="text-sm text-gray-500">
                                        3 jours - à prendre dans un délai de un mois à compter de la date de la naissance
                                    </p>
                                )}
                                {data.type_conge === 'deces' && (
                                    <p className="text-sm text-gray-500">
                                        3 jours (décès conjoint, d'un enfant, d'un petit-enfant, d'un ascendant), dont un jour payé et deux jours
                                        déduits automatiquement du congé
                                    </p>
                                )}
                                {data.type_conge === 'sans_solde' && (
                                    <p className="text-sm text-gray-500">Ce congé sera déduit automatiquement de votre salaire</p>
                                )}
                                {data.type_conge === 'recuperation' && (
                                    <p className="text-sm text-gray-500">La journée à récupérer doit se faire dans un délai de 2 mois</p>
                                )}
                                {data.type_conge === 'maladie' && (
                                    <p className="text-sm text-gray-500">
                                        Arrêt de travail à déclarer dans les 48 heures, avec le certificat médical. Il ne réduit pas vos jours de
                                        congé.
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1">
                                <label htmlFor="nbr_jours" className="text-sm font-medium">
                                    Nombre de jours
                                </label>
                                <Input id="nbr_jours" type="text" name="nbr_jours" value={formatDays(nbr_jours)} className="w-full" readOnly />
                                <p className="text-xs text-gray-500">Jours ouvrés, hors week-ends et jours fériés</p>
                                {beforeEligibility && balance?.eligibleFrom && (
                                    <p className="text-xs text-amber-600">
                                        Le congé annuel n'est dû qu'après 6 mois de service, soit à partir du{' '}
                                        {parseISODate(balance.eligibleFrom)?.toLocaleDateString('fr-FR')}. Cette demande ne pourra pas être acceptée
                                        en l'état.
                                    </p>
                                )}
                                {!beforeEligibility && exceedsBalance && availableAtStart !== null && (
                                    <p className="text-xs text-amber-600">
                                        Cette demande utilise {requestedFromAllowance} jour(s), mais vous n'aurez acquis que{' '}
                                        {formatDays(Math.max(0, availableAtStart))} jour(s) disponibles à cette date. Elle ne pourra pas être acceptée
                                        en l'état.
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-1">
                                <label htmlFor="date_debut" className="text-sm font-medium">
                                    Date de début
                                </label>
                                <Input
                                    id="date_debut"
                                    type="date"
                                    name="date_debut"
                                    value={data.date_debut}
                                    min={isAdmin ? undefined : earliestStart}
                                    onChange={(e) => setData('date_debut', e.target.value)}
                                    className={errors.date_debut ? 'border-red-500' : ''}
                                    required
                                />
                                {errors.date_debut && <p className="text-xs text-red-500">{errors.date_debut}</p>}
                            </div>

                            <div className="space-y-1">
                                <label htmlFor="date_fin" className="text-sm font-medium">
                                    Date de fin
                                </label>
                                <Input
                                    id="date_fin"
                                    type="date"
                                    name="date_fin"
                                    value={data.date_fin}
                                    min={data.date_debut || undefined}
                                    onChange={(e) => setData('date_fin', e.target.value)}
                                    className={errors.date_fin ? 'border-red-500' : ''}
                                    required
                                    readOnly={Boolean(fixedLength)}
                                />
                                {errors.date_fin && <p className="text-xs text-red-500">{errors.date_fin}</p>}
                                {fixedLength && (
                                    <p className="text-sm text-gray-500">
                                        La date de fin est automatiquement calculée en fonction de la date de début
                                    </p>
                                )}
                            </div>
                        </div>

                        {rules.documentTypes.includes(data.type_conge) && (
                            <div className="space-y-1">
                                <label htmlFor="document" className="text-sm font-medium">
                                    Justificatif
                                </label>
                                <Input
                                    id="document"
                                    type="file"
                                    accept={rules.documentMimes.map((ext) => `.${ext}`).join(',')}
                                    onChange={(e) => setData('document', e.target.files?.[0] ?? null)}
                                    className={errors.document ? 'border-red-500' : ''}
                                />
                                <p className="text-xs text-gray-500">
                                    Acte de mariage, de naissance ou de décès (PDF, JPG ou PNG). Vous pouvez aussi l'ajouter plus tard ; il est
                                    nécessaire avant l'acceptation par les RH.
                                </p>
                                {errors.document && <p className="text-xs text-red-500">{errors.document}</p>}
                            </div>
                        )}

                        {halfDays && (
                            <HalfDayFields
                                start={data.date_debut}
                                end={data.date_fin}
                                startHalf={data.start_half}
                                endHalf={data.end_half}
                                onChange={(halves) => setData((prev) => ({ ...prev, ...halves }))}
                            />
                        )}

                        <div className="space-y-1">
                            <label htmlFor="comment" className="text-sm font-medium">
                                Commentaire
                            </label>
                            <Textarea
                                id="comment"
                                name="comment"
                                value={data.comment}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('comment', e.target.value)}
                                placeholder="Entrez la raison de votre demande..."
                                className={`min-h-[100px] ${errors.comment ? 'border-red-500' : ''}`}
                            />
                            {errors.comment && <p className="text-xs text-red-500">{errors.comment}</p>}
                        </div>
                    </form>
                </div>

                <DialogFooter className="mt-4 flex-col gap-2 sm:flex-row">
                    <Button variant="outline" type="button" onClick={close} disabled={processing} className="w-full sm:w-auto">
                        Annuler
                    </Button>
                    <Button type="submit" disabled={processing} onClick={handleSubmit} className="w-full sm:w-auto">
                        {processing ? 'Envoi...' : 'Soumettre la demande'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default AddDemandeModal;
