import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { TypeConge, allowanceDays, countWorkingDays, endAfterWorkingDays, nextWorkingDay, toISODate, todayISO, useLeaveRules } from '@/lib/leave';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';

type FormErrors = Record<string, string | string[]>;

const firstError = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value);

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

    const [selectedUserId, setSelectedUserId] = useState<string>('');
    const [date_debut, setDateDebut] = useState('');
    const [date_fin, setDateFin] = useState('');
    const [type_conge, setTypeConge] = useState<TypeConge>('annuel');
    const [comment, setComment] = useState('');
    const [errors, setErrors] = useState<FormErrors>({});
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [loading, setLoading] = useState(false);

    // Fixed-length leave types (mariage, naissance, décès) get their end date computed.
    const fixedLength = rules.typeLimits[type_conge];
    const nbr_jours = date_debut && date_fin ? countWorkingDays(date_debut, date_fin, rules) : 0;

    // Warn (without blocking) when an employee's request would exceed their remaining days this year.
    // The admin still can't accept it; admins filing for someone else don't see their own balance here.
    const requestedFromAllowance = allowanceDays(type_conge, nbr_jours, rules);
    const exceedsBalance =
        !isAdmin &&
        rules.remainingDays !== null &&
        date_debut.startsWith(String(new Date().getFullYear())) &&
        requestedFromAllowance > rules.remainingDays;

    // A changed date or type makes earlier date errors stale.
    useEffect(() => {
        setErrors((prev) => {
            if (!prev.date_debut && !prev.date_fin) return prev;
            const rest = { ...prev };
            delete rest.date_debut;
            delete rest.date_fin;
            return rest;
        });
    }, [date_debut, date_fin, type_conge]);

    // Reset the form when the leave type changes; fixed-length types start on the next working day.
    useEffect(() => {
        setComment(type_conge === 'recuperation' ? 'Veuillez préciser les dates à récupérer' : '');

        if (fixedLength) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            setDateDebut(toISODate(nextWorkingDay(tomorrow, rules)));
        } else {
            setDateDebut('');
            setDateFin('');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [type_conge]);

    // Keep the end date of fixed-length leave in sync with the start date.
    useEffect(() => {
        if (fixedLength && date_debut) {
            setDateFin(endAfterWorkingDays(date_debut, fixedLength, rules));
        }
    }, [date_debut, fixedLength, rules]);

    const validateForm = () => {
        const newErrors: FormErrors = {};

        if (isAdmin && !selectedUserId) {
            newErrors.user_id = 'Veuillez sélectionner un employé';
        }
        if (!date_debut) {
            newErrors.date_debut = 'La date de début est obligatoire';
        } else if (!isAdmin && date_debut < todayISO()) {
            newErrors.date_debut = 'La date de début ne peut pas être dans le passé';
        }
        if (!date_fin) {
            newErrors.date_fin = 'La date de fin est obligatoire';
        } else if (date_debut && date_fin < date_debut) {
            newErrors.date_fin = 'La date de fin doit être après la date de début';
        } else if (date_debut && nbr_jours === 0) {
            newErrors.date_fin = 'La période choisie ne contient aucun jour ouvré';
        } else if (fixedLength && nbr_jours > fixedLength) {
            newErrors.date_fin = `Ce type de congé ne peut pas dépasser ${fixedLength} jours ouvrés`;
        }
        if (type_conge === 'recuperation' && !comment) {
            newErrors.comment = 'Veuillez renseigner les dates sujet de récupération';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const resetForm = () => {
        setSelectedUserId('');
        setDateDebut('');
        setDateFin('');
        setTypeConge('annuel');
        setComment('');
        setErrors({});
    };

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        setMessage(null);

        if (!validateForm()) {
            return;
        }

        setLoading(true);

        try {
            // The server sets the request date, status, owner (unless an admin picks one) and day count.
            const formData = {
                date_debut,
                date_fin,
                type_conge,
                comment,
                ...(isAdmin ? { user_id: selectedUserId } : {}),
            };

            const response = await axios.post('/demandes', formData, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                withCredentials: true,
            });

            if (response.data) {
                setMessage({ type: 'success', text: 'Demande ajoutée avec succès' });
                resetForm();

                setTimeout(() => {
                    onClose();
                    router.reload();
                }, 1500);
            }
        } catch (error) {
            console.error('Error adding demande:', error);
            if (axios.isAxiosError(error)) {
                if (error.response?.status === 419) {
                    setMessage({ type: 'error', text: 'Session expirée. Veuillez rafraîchir la page et réessayer.' });
                } else if (error.response?.data?.errors) {
                    setErrors(error.response.data.errors);
                    setMessage({ type: 'error', text: 'Veuillez corriger les erreurs dans le formulaire' });
                } else {
                    setMessage({ type: 'error', text: error.response?.data?.message || "Une erreur s'est produite lors de l'ajout de la demande" });
                }
            } else {
                setMessage({ type: 'error', text: "Une erreur inattendue s'est produite" });
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="flex max-h-[90vh] w-[95vw] flex-col sm:w-[85vw] md:w-[75vw] lg:w-[60vw] xl:w-[50vw]">
                <DialogHeader>
                    <DialogTitle>Ajouter une demande de congé</DialogTitle>
                    <DialogDescription>Remplissez le formulaire ci-dessous pour soumettre une nouvelle demande de congé.</DialogDescription>
                </DialogHeader>
                <div className="-mr-4 flex-1 overflow-y-auto pr-4">
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {message && (
                            <div className={`rounded p-2 ${message.type === 'success' ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800'}`}>
                                {message.text}
                            </div>
                        )}

                        {isAdmin && (
                            <div className="space-y-1">
                                <label htmlFor="user_id" className="text-sm font-medium">
                                    Employé
                                </label>
                                <Select value={selectedUserId} onValueChange={setSelectedUserId}>
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
                                {errors.user_id && <p className="text-xs text-red-500">{firstError(errors.user_id)}</p>}
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-1">
                                <label htmlFor="type_conge" className="text-sm font-medium">
                                    Type de congé
                                </label>
                                <Select value={type_conge} onValueChange={(value: TypeConge) => setTypeConge(value)}>
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
                                    </SelectContent>
                                </Select>
                                {type_conge === 'annuel' && (
                                    <p className="text-sm text-gray-500">Déduit de votre solde de {rules.maxDaysPerYear} jours ouvrés par an</p>
                                )}
                                {type_conge === 'mariage' && (
                                    <p className="text-sm text-gray-500">
                                        4 jours dont deux jours payés et deux jours déduits automatiquement du congé
                                    </p>
                                )}
                                {type_conge === 'naissance' && (
                                    <p className="text-sm text-gray-500">
                                        3 jours - à prendre dans un délai de un mois à compter de la date de la naissance
                                    </p>
                                )}
                                {type_conge === 'deces' && (
                                    <p className="text-sm text-gray-500">
                                        3 jours (décès conjoint, d'un enfant, d'un petit-enfant, d'un ascendant), dont un jour payé et deux jours
                                        déduits automatiquement du congé
                                    </p>
                                )}
                                {type_conge === 'sans_solde' && (
                                    <p className="text-sm text-gray-500">Ce congé sera déduit automatiquement de votre salaire</p>
                                )}
                                {type_conge === 'recuperation' && (
                                    <p className="text-sm text-gray-500">La journée à récupérer doit se faire dans un délai de 2 mois</p>
                                )}
                            </div>

                            <div className="space-y-1">
                                <label htmlFor="nbr_jours" className="text-sm font-medium">
                                    Nombre de jours
                                </label>
                                <Input id="nbr_jours" type="number" name="nbr_jours" value={nbr_jours} className="w-full" readOnly />
                                <p className="text-xs text-gray-500">Jours ouvrés, hors week-ends et jours fériés</p>
                                {exceedsBalance && (
                                    <p className="text-xs text-amber-600">
                                        Cette demande utilise {requestedFromAllowance} jour(s) de votre solde, mais il ne vous en reste que{' '}
                                        {rules.remainingDays}. Elle ne pourra pas être acceptée en l'état.
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
                                    value={date_debut}
                                    min={isAdmin ? undefined : todayISO()}
                                    onChange={(e) => setDateDebut(e.target.value)}
                                    className={errors.date_debut ? 'border-red-500' : ''}
                                    required
                                />
                                {errors.date_debut && <p className="text-xs text-red-500">{firstError(errors.date_debut)}</p>}
                            </div>

                            <div className="space-y-1">
                                <label htmlFor="date_fin" className="text-sm font-medium">
                                    Date de fin
                                </label>
                                <Input
                                    id="date_fin"
                                    type="date"
                                    name="date_fin"
                                    value={date_fin}
                                    min={date_debut || undefined}
                                    onChange={(e) => setDateFin(e.target.value)}
                                    className={errors.date_fin ? 'border-red-500' : ''}
                                    required
                                    readOnly={Boolean(fixedLength)}
                                />
                                {errors.date_fin && <p className="text-xs text-red-500">{firstError(errors.date_fin)}</p>}
                                {fixedLength && (
                                    <p className="text-sm text-gray-500">
                                        La date de fin est automatiquement calculée en fonction de la date de début
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-1">
                            <label htmlFor="comment" className="text-sm font-medium">
                                Commentaire
                            </label>
                            <Textarea
                                id="comment"
                                name="comment"
                                value={comment || ''}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setComment(e.target.value)}
                                placeholder="Entrez la raison de votre demande..."
                                className={`min-h-[100px] ${errors.comment ? 'border-red-500' : ''}`}
                            />
                            {errors.comment && <p className="text-xs text-red-500">{firstError(errors.comment)}</p>}
                        </div>
                    </form>
                </div>

                <DialogFooter className="mt-4 flex-col gap-2 sm:flex-row">
                    <Button variant="outline" type="button" onClick={onClose} disabled={loading} className="w-full sm:w-auto">
                        Annuler
                    </Button>
                    <Button type="submit" disabled={loading} onClick={handleSubmit} className="w-full sm:w-auto">
                        {loading ? 'Envoi...' : 'Soumettre la demande'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

// Also add a default export for backward compatibility
export default AddDemandeModal;
