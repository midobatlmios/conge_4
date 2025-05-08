import {useState, useEffect} from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Demande } from "@/types";
import axios from "axios";

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
        date_demande: new Date().toISOString().split('T')[0], 
        date_debut: "", 
        date_fin: "", 
        nbr_jours: 0,
        annee: new Date().getFullYear(),
        type_conge: "mariage",
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

    // Handle type_conge changes
    const handleTypeCongeChange = (value: "mariage" | "naissance" | "deces" | "sans_solde" | "recuperation") => {
        console.log('Changing type_conge to:', value); // Debug log
        
        // Update type_conge first
        setFormData(prev => {
            console.log('Previous form data:', prev); // Debug log
            const newData = {
                ...prev,
                type_conge: value
            };
            console.log('New form data:', newData); // Debug log
            return newData;
        });

        // Reset dates when type changes
        if (formData.date_debut) {
            const startDate = new Date(formData.date_debut);
            const endDate = new Date(startDate);

            switch (value) {
                case "mariage":
                    endDate.setDate(endDate.getDate() + 3); // 4 days total
                    break;
                case "naissance":
                    endDate.setDate(endDate.getDate() + 2); // 3 days total
                    break;
                case "deces":
                    endDate.setDate(endDate.getDate() + 2); // 3 days total
                    break;
                default:
                    return;
            }

            setFormData(prev => ({
                ...prev,
                date_fin: endDate.toISOString().split('T')[0]
            }));
        }
    };

    // Handle date_debut changes
    const handleDateDebutChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newDateDebut = e.target.value;
        setFormData(prev => ({
            ...prev,
            date_debut: newDateDebut
        }));

        if (newDateDebut) {
            const startDate = new Date(newDateDebut);
            const endDate = new Date(startDate);

            switch (formData.type_conge) {
                case "mariage":
                    endDate.setDate(endDate.getDate() + 3); // 4 days total
                    break;
                case "naissance":
                    endDate.setDate(endDate.getDate() + 2); // 3 days total
                    break;
                case "deces":
                    endDate.setDate(endDate.getDate() + 2); // 3 days total
                    break;
                default:
                    return;
            }

            setFormData(prev => ({
                ...prev,
                date_fin: endDate.toISOString().split('T')[0]
            }));
        }
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
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            console.log('Current form data before submit:', formData); // Debug log
            
            const dataToSend = {
                _method: 'PUT',
                date_demande: formData.date_demande,
                date_debut: formData.date_debut,
                date_fin: formData.date_fin,
                type_conge: formData.type_conge,
                etat: formData.etat,
                comment: formData.comment
            };
            
            console.log('Sending update data:', dataToSend); // Debug log
            
            const response = await axios.post(`/demandes/${demande?.id}`, dataToSend, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                withCredentials: true
            });
            
            console.log('Server response:', response.data); // Debug log
            
            if (response.request?.responseURL) {
                setMessage({type: "success", text: "Demande mise à jour avec succès"});
                const updatedDemande = {
                    ...formData
                };
                
                onUpdate(updatedDemande);
                setTimeout(() => {
                    onClose();
                }, 1500);
            } else {
                const updatedDemande = {
                    ...formData,
                    ...response.data
                };
                
                setMessage({type: "success", text: "Demande mise à jour avec succès"});
                onUpdate(updatedDemande);
                setTimeout(() => {
                    onClose();
                }, 1500);
            }
            
        } catch (error) {
            console.error("Error updating demande:", error);
            
            if (axios.isAxiosError(error) && error.response) {
                console.log('Error response:', error.response.data); // Debug log
                if (error.response.status === 422 && error.response.data.errors) {
                    setErrors(error.response.data.errors);
                    const errorMessage = Object.values(error.response.data.errors).flat().join(', ');
                    setMessage({type: "error", text: errorMessage});
                } else if (error.response.status === 419) {
                    setMessage({
                        type: "error", 
                        text: "Erreur de token CSRF. Veuillez rafraîchir la page et réessayer."
                    });
                } else {
                    setMessage({
                        type: "error", 
                        text: error.response.data.message || "Une erreur s'est produite lors de la mise à jour de la demande"
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
            <DialogContent className="max-h-[90vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Modifier la demande</DialogTitle>
                </DialogHeader>
                <div className="flex-1 overflow-y-auto pr-4 -mr-4">
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {message && ( 
                            <div className={`p-2 rounded ${message.type === "success" ? "bg-green-200 text-green-800" : "bg-red-200 text-red-800"}`}>
                                {message.text}
                            </div>
                        )}
                        
                        <div className="space-y-1">
                            <label htmlFor="type_conge" className="text-sm font-medium">Type de congé</label>
                            <Select
                                value={formData.type_conge}
                                onValueChange={(value: "mariage" | "naissance" | "deces" | "sans_solde" | "recuperation") => {
                                    console.log('Select onValueChange:', value); // Debug log
                                    handleTypeCongeChange(value);
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner un type de congé" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="mariage">Congé de Mariage (4 jours max)</SelectItem>
                                    <SelectItem value="naissance">Congé de Naissance (3 jours)</SelectItem>
                                    <SelectItem value="deces">Congé de Décès (3 jours max)</SelectItem>
                                    <SelectItem value="sans_solde">Congé sans solde</SelectItem>
                                    <SelectItem value="recuperation">Récupération</SelectItem>
                                </SelectContent>
                            </Select>
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
                                readOnly={formData.type_conge === "mariage" || formData.type_conge === "naissance" || formData.type_conge === "deces"}
                            />
                            {errors.date_fin && <p className="text-red-500 text-xs">{errors.date_fin[0]}</p>}
                            {(formData.type_conge === "mariage" || formData.type_conge === "naissance" || formData.type_conge === "deces") && (
                                <p className="text-sm text-gray-500">La date de fin est automatiquement calculée en fonction de la date de début</p>
                            )}
                        </div>
                        
                        <div className="space-y-1">
                            <label htmlFor="nbr_jours" className="text-sm font-medium">Nombre de jours</label>
                            <Input 
                                id="nbr_jours"
                                type="number" 
                                name="nbr_jours" 
                                value={formData.nbr_jours} 
                                className="w-full"
                                readOnly 
                            />
                            <p className="text-gray-500 text-xs">Calculé automatiquement à partir des dates</p>
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

                <DialogFooter className="mt-4">
                    <Button variant="outline" type="button" onClick={onClose} disabled={loading}>
                        Annuler
                    </Button>
                    <Button type="submit" disabled={loading} onClick={handleSubmit}>
                        {loading ? "Mise à jour..." : "Modifier demande"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default EditDemandeModal;