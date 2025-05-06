import {useState, useEffect} from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Demande } from "@/components/demandes/columns";
import axios from "axios";

interface EditDemandeModalProps {
    isOpen: boolean;
    onClose: () => void;
    demande: Demande | null;
    onUpdate: (updateDemande: Demande) => void;
}

const EditDemandeModal: React.FC<EditDemandeModalProps> = ({ isOpen, onClose, demande, onUpdate }) => {
    const [formData, setFormData] = useState<Demande>({
        id: 0, 
        user_id: 0,
        date_demande: new Date().toISOString().split('T')[0], 
        date_debut: "", 
        date_fin: "", 
        nbr_jours: 0,
        annee: new Date().getFullYear(),
        etat: "en attente", 
        valide_par: null,
        comment: "",
        user: {
            name: "",
            email: "",
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

    // Calculate nbr_jours when date_debut or date_fin changes
    useEffect(() => {
        if (formData.date_debut && formData.date_fin) {
            const startDate = new Date(formData.date_debut);
            const endDate = new Date(formData.date_fin);
            
            // Calculate the difference in days
            const diffTime = Math.abs(endDate.getTime() - startDate.getTime());
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include the start day
            
            setFormData(prev => ({
                ...prev,
                nbr_jours: diffDays
            }));
        }
    }, [formData.date_debut, formData.date_fin]);

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setMessage(null);
        setErrors({});

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            const dataToSend = {
                _method: 'PUT',
                date_demande: formData.date_demande || new Date().toISOString().split('T')[0],
                date_debut: formData.date_debut,
                date_fin: formData.date_fin,
                etat: formData.etat,
                comment: formData.comment
            };
            
            const response = await axios.post(`/demandes/${demande?.id}`, dataToSend, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                withCredentials: true
            });
            
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
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier la demande</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-2">
                    {message && ( 
                        <div className={`p-2 rounded ${message.type === "success" ? "bg-green-200 text-green-800" : "bg-red-200 text-red-800"}`}>
                            {message.text}
                        </div>
                    )}
                    
                    <div className="flex flex-col space-y-1">
                        <label htmlFor="date_debut">Date de début</label>
                        <Input 
                            id="date_debut"
                            type="date" 
                            name="date_debut" 
                            value={formData.date_debut} 
                            onChange={handleChange} 
                            className={`w-full ${errors.date_debut ? 'border-red-500' : ''}`}
                            required 
                        />
                        {errors.date_debut && <p className="text-red-500 text-sm">{errors.date_debut[0]}</p>}
                    </div>
                    
                    <div className="flex flex-col space-y-1">
                        <label htmlFor="date_fin">Date de fin</label>
                        <Input 
                            id="date_fin"
                            type="date" 
                            name="date_fin" 
                            value={formData.date_fin} 
                            onChange={handleChange} 
                            className={`w-full ${errors.date_fin ? 'border-red-500' : ''}`}
                            required 
                        />
                        {errors.date_fin && <p className="text-red-500 text-sm">{errors.date_fin[0]}</p>}
                    </div>
                    
                    <div className="flex flex-col space-y-1">
                        <label htmlFor="nbr_jours">Nombre de jours (calculé automatiquement)</label>
                        <Input 
                            id="nbr_jours"
                            type="number" 
                            name="nbr_jours" 
                            value={formData.nbr_jours} 
                            onChange={handleChange} 
                            className="w-full"
                            readOnly 
                        />
                        {/* This is calculated by Laravel so we don't show errors for it */}
                    </div>
                    
                    <div className="flex flex-col space-y-1">
                        <label htmlFor="annee">Année</label>
                        <Input 
                            id="annee"
                            type="number" 
                            name="annee" 
                            value={formData.annee} 
                            onChange={handleChange} 
                            className="w-full"
                            readOnly // Make readonly since it's not fillable in the model
                        />
                    </div>
                    
                    <div className="flex flex-col space-y-1">
                        <label htmlFor="comment">Commentaire</label>
                        <Textarea 
                            id="comment"
                            name="comment"
                            value={formData.comment || ''}
                            onChange={handleChange}
                            placeholder="Entrez la raison de votre demande..."
                            className={`w-full min-h-[100px] ${errors.comment ? 'border-red-500' : ''}`}
                        />
                        {errors.comment && <p className="text-red-500 text-sm">{errors.comment[0]}</p>}
                    </div>
                    
                    <div className="flex flex-col space-y-1">
                        <label htmlFor="etat">État</label>
                        <select 
                            id="etat"
                            name="etat" 
                            value={formData.etat} 
                            onChange={handleChange} 
                            className={`border p-2 rounded w-full ${errors.etat ? 'border-red-500' : ''}`}
                            required
                        >
                            <option value="en attente">En attente</option>
                            <option value="acceptée">Acceptée</option>
                            <option value="refusée">Refusée</option>
                        </select>
                        {errors.etat && <p className="text-red-500 text-sm">{errors.etat[0]}</p>}
                    </div>
                    
                    <DialogFooter>
                        <Button variant="outline" type="button" onClick={onClose} disabled={loading}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={loading}>
                            {loading ? "Mise à jour..." : "Modifier demande"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
};

export default EditDemandeModal;