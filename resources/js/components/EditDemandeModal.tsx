import {useState, useEffect} from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Demande } from "@/types";
import axios from "axios";
import { TypeConge, countWorkingDays, endAfterWorkingDays, todayISO, useLeaveRules } from "@/lib/leave";

interface EditDemandeModalProps {
    isOpen: boolean;
    onClose: () => void;
    demande: Demande | null;
    onUpdate: (updateDemande: Demande) => void;
    isAdmin?: boolean;
}

const EditDemandeModal: React.FC<EditDemandeModalProps> = ({ isOpen, onClose, demande, onUpdate, isAdmin = false }) => {
    const [formData, setFormData] = useState<Demande>({
        id: 0, 
        user_id: 0,
        date_demande: todayISO(),
        date_debut: "", 
        date_fin: "", 
        nbr_jours: 0,
        annee: new Date().getFullYear(),
        type_conge: "annuel",
        etat: "en attente", 
        valide_par: null,
        comment: "",
        user: {
            name: "",
            email: "",
            role: "user",
            remaining_days: 18
        }
    });
    
    const [message, setMessage] = useState<{type: "success" | "error" | "default"; text: string} | null>(null);
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    useEffect(() => {
        if(demande) {
            setFormData(demande);
        }
    }, [demande]);

    const rules = useLeaveRules();
    const fixedLength = rules.typeLimits[formData.type_conge];
    const nbrJours = formData.date_debut && formData.date_fin
        ? countWorkingDays(formData.date_debut, formData.date_fin, rules)
        : 0;

    // Fixed-length leave types (mariage, naissance, décès) get their end date computed in working days.
    const computeEndDate = (type: TypeConge, start: string, currentEnd: string) => {
        const limit = rules.typeLimits[type];
        return limit && start ? endAfterWorkingDays(start, limit, rules) : currentEnd;
    };

    const handleTypeCongeChange = (value: TypeConge) => {
        setFormData(prev => ({
            ...prev,
            type_conge: value,
            date_fin: computeEndDate(value, prev.date_debut, prev.date_fin),
        }));
        clearDateErrors();
    };

    // A changed date or type makes earlier date errors stale.
    const clearDateErrors = () => {
        setErrors(prev => {
            const rest = { ...prev };
            delete rest.date_debut;
            delete rest.date_fin;
            return rest;
        });
    };

    const handleDateDebutChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newDateDebut = e.target.value;
        setFormData(prev => ({
            ...prev,
            date_debut: newDateDebut,
            date_fin: computeEndDate(prev.type_conge, newDateDebut, prev.date_fin),
        }));
        clearDateErrors();
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
        const {name, value} = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: name === "nbr_jours" || name === "annee" || name === "user_id" 
                ? parseInt(value) 
                : value,
        }));
        
        if (errors[name]) {
            setErrors(prev => {
                const newErrors = {...prev};
                delete newErrors[name];
                return newErrors;
            });
        }
    };

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setMessage(null);
        setErrors({});

        try {
            const dataToSend = {
                _method: 'PUT',
                date_debut: formData.date_debut,
                date_fin: formData.date_fin,
                type_conge: formData.type_conge,
                etat: formData.etat,
                comment: formData.comment
            };
            
            const response = await axios.post(`/demandes/${demande?.id}`, dataToSend, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                withCredentials: true
            });
            
            if (response.data) {
                setMessage({type: "success", text: "Demande mise à jour avec succès"});
                const updatedDemande = {
                    ...formData,
                    ...response.data
                };
                
                onUpdate(updatedDemande);
                setTimeout(() => {
                    onClose();
                }, 1500);
            }
        } catch (error) {
            console.error("Error updating demande:", error);
            
            if (axios.isAxiosError(error)) {
                if (error.response?.status === 419) {
                    setMessage({
                        type: "error", 
                        text: "Session expirée. Veuillez rafraîchir la page et réessayer."
                    });
                } else if (error.response?.status === 422 && error.response.data.errors) {
                    setErrors(error.response.data.errors);
                    const errorMessage = Object.values(error.response.data.errors).flat().join(', ');
                    setMessage({type: "error", text: errorMessage});
                } else {
                    setMessage({
                        type: "error", 
                        text: error.response?.data?.message || "Une erreur s'est produite lors de la mise à jour de la demande"
                    });
                }
            } else {
                setMessage({type: "error", text: "Une erreur s'est produite lors de la mise à jour de la demande"});
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] flex flex-col w-[95vw] sm:w-[85vw] md:w-[75vw] lg:w-[60vw] xl:w-[50vw]">
                <DialogHeader>
                    <DialogTitle>Modifier la demande de congé</DialogTitle>
                    <DialogDescription>
                        Modifiez les informations de la demande de congé ci-dessous.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex-1 overflow-y-auto pr-4 -mr-4">
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {message && ( 
                            <div className={`p-2 rounded ${message.type === "success" ? "bg-green-200 text-green-800" : "bg-red-200 text-red-800"}`}>
                                {message.text}
                            </div>
                        )}
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label htmlFor="type_conge" className="text-sm font-medium">Type de congé</label>
                                <Select
                                    value={formData.type_conge}
                                    onValueChange={(value: TypeConge) => {
                                        handleTypeCongeChange(value);
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sélectionner un type de congé" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="annuel">Congé annuel</SelectItem>
                                        <SelectItem value="mariage">Congé de Mariage (4 jours max)</SelectItem>
                                        <SelectItem value="naissance">Congé de Naissance (3 jours max)</SelectItem>
                                        <SelectItem value="deces">Congé de Décès (3 jours max)</SelectItem>
                                        <SelectItem value="sans_solde">Congé sans solde</SelectItem>
                                        <SelectItem value="recuperation">Récupération</SelectItem>
                                    </SelectContent>
                                </Select>
                                {formData.type_conge === "annuel" && (
                                    <p className="text-sm text-gray-500">Déduit du solde de {rules.maxDaysPerYear} jours ouvrés par an</p>
                                )}
                                {formData.type_conge === "mariage" && (
                                    <p className="text-sm text-gray-500">4 jours dont deux jours payés et deux jours déduits automatiquement du congé</p>
                                )}
                                {formData.type_conge === "naissance" && (
                                    <p className="text-sm text-gray-500">3 jours - à prendre dans un délai de un mois à compter de la date de la naissance</p>
                                )}
                                {formData.type_conge === "deces" && (
                                    <p className="text-sm text-gray-500">3 jours (décès conjoint, d'un enfant, d'un petit-enfant, d'un ascendant), dont un jour payé et deux jours déduits automatiquement du congé</p>
                                )}
                                {formData.type_conge === "sans_solde" && (
                                    <p className="text-sm text-gray-500">Ce congé sera déduit automatiquement de votre salaire</p>
                                )}
                                {formData.type_conge === "recuperation" && (
                                    <p className="text-sm text-gray-500">La journée à récupérer doit se faire dans un délai de 2 mois</p>
                                )}
                            </div>
                            
                            <div className="space-y-1">
                                <label htmlFor="nbr_jours" className="text-sm font-medium">Nombre de jours</label>
                                <Input 
                                    id="nbr_jours"
                                    type="number" 
                                    name="nbr_jours" 
                                    value={nbrJours}
                                    className="w-full"
                                    readOnly
                                />
                                <p className="text-gray-500 text-xs">Jours ouvrés, hors week-ends et jours fériés</p>
                            </div>
                        </div>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label htmlFor="date_debut" className="text-sm font-medium">Date de début</label>
                                <Input 
                                    id="date_debut"
                                    type="date" 
                                    name="date_debut" 
                                    value={formData.date_debut} 
                                    onChange={handleDateDebutChange} 
                                    className={errors.date_debut ? "border-red-500" : ""}
                                    required 
                                />
                                {errors.date_debut && <p className="text-red-500 text-xs">{errors.date_debut[0]}</p>}
                            </div>
                            
                            <div className="space-y-1">
                                <label htmlFor="date_fin" className="text-sm font-medium">Date de fin</label>
                                <Input 
                                    id="date_fin"
                                    type="date" 
                                    name="date_fin" 
                                    value={formData.date_fin} 
                                    onChange={handleChange} 
                                    className={errors.date_fin ? "border-red-500" : ""}
                                    required 
                                    readOnly={Boolean(fixedLength)}
                                />
                                {errors.date_fin && <p className="text-red-500 text-xs">{errors.date_fin[0]}</p>}
                                {fixedLength && (
                                    <p className="text-sm text-gray-500">La date de fin est automatiquement calculée en fonction de la date de début</p>
                                )}
                            </div>
                        </div>
                        
                        <div className="space-y-1">
                            <label htmlFor="comment" className="text-sm font-medium">Commentaire</label>
                            <Textarea 
                                id="comment"
                                name="comment"
                                value={formData.comment || ''}
                                onChange={handleChange}
                                placeholder="Entrez la raison de votre demande..."
                                className={`min-h-[100px] ${errors.comment ? 'border-red-500' : ''}`}
                            />
                            {errors.comment && <p className="text-red-500 text-xs">{errors.comment[0]}</p>}
                        </div>
                        
                        {/* État field: only editable by admin */}
                        {isAdmin ? (
                            <div className="space-y-1">
                                <label htmlFor="etat" className="text-sm font-medium">État</label>
                                <select 
                                    id="etat"
                                    name="etat" 
                                    value={formData.etat} 
                                    onChange={handleChange} 
                                    className={`w-full p-2 border rounded ${errors.etat ? 'border-red-500' : ''}`}
                                    required
                                >
                                    <option value="en attente">En attente</option>
                                    <option value="acceptée">Acceptée</option>
                                    <option value="refusée">Refusée</option>
                                </select>
                                {errors.etat && <p className="text-red-500 text-xs">{errors.etat[0]}</p>}
                            </div>
                        ) : (
                            <div className="space-y-1">
                                <label htmlFor="etat" className="text-sm font-medium">État</label>
                                <Input
                                    id="etat"
                                    type="text"
                                    value={formData.etat}
                                    readOnly
                                    className="bg-gray-100 cursor-not-allowed"
                                />
                            </div>
                        )}
                    </form>
                </div>

                <DialogFooter className="mt-4 flex-col sm:flex-row gap-2">
                    <Button variant="outline" type="button" onClick={onClose} disabled={loading} className="w-full sm:w-auto">
                        Annuler
                    </Button>
                    <Button type="submit" disabled={loading} onClick={handleSubmit} className="w-full sm:w-auto">
                        {loading ? "Mise à jour..." : "Modifier demande"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default EditDemandeModal;