import { useState, useEffect } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";

export const AddDemandeModal = ({ isOpen, onClose }: { isOpen: boolean, onClose: () => void }) => {
    // Format today's date as YYYY-MM-DD for the default date_demande value
    const today = new Date();
    const formattedToday = today.toISOString().split('T')[0];
    
    // Get user ID from meta tag
    const userId = document.querySelector('meta[name="user-id"]')?.getAttribute("content");
    
    const [date_demande, setDateDemande] = useState(formattedToday);
    const [date_debut, setDateDebut] = useState("");
    const [date_fin, setDateFin] = useState("");
    const [nbr_jours, setNbJours] = useState(0);
    const [annee, setAnnee] = useState("");
    const [etat, setEtat] = useState("en attente");
    const [comment, setComment] = useState("");
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);
    const [loading, setLoading] = useState(false);

    // Calculate number of days between two dates
    useEffect(() => {
        if (date_debut && date_fin) {
            const startDate = new Date(date_debut);
            const endDate = new Date(date_fin);
            
            // Check if dates are valid
            if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
                // Calculate difference in days
                const diffTime = Math.abs(endDate.getTime() - startDate.getTime());
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include both start and end dates
                setNbJours(diffDays);
                
                // Set year from start date
                setAnnee(startDate.getFullYear().toString());
            }
        }
    }, [date_debut, date_fin]);
    
    // Validate form
    const validateForm = () => {
        const newErrors: Record<string, string> = {};
        
        if (!date_demande) {
            newErrors.date_demande = "Date de demande is required";
        }
        
        if (!date_debut) {
            newErrors.date_debut = "Date de début is required";
        }
        
        if (!date_fin) {
            newErrors.date_fin = "Date de fin is required";
        }
        
        if (date_debut && date_fin) {
            const startDate = new Date(date_debut);
            const endDate = new Date(date_fin);
            
            if (endDate < startDate) {
                newErrors.date_fin = "Date de fin must be after Date de début";
            }
        }
        
        if (nbr_jours <= 0) {
            newErrors.nbr_jours = "Nombre de jours must be greater than 0";
        }
        
        if (!annee) {
            newErrors.annee = "Année is required";
        }
        
        if (!etat) {
            newErrors.etat = "Etat is required";
        }
        
        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        setMessage(null);
        
        if (!userId) {
            setMessage({ type: "error", text: "User ID not found. Please make sure you are logged in." });
            return;
        }
        
        if (!validateForm()) {
            return;
        }
        
        setLoading(true);
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
        if (!csrfToken) {
            setMessage({ type: "error", text: "CSRF token not found" });
            setLoading(false);
            return;
        }
        
        try {
            console.log('Submitting demande:', {
                date_demande,
                date_debut,
                date_fin,
                nbr_jours,
                annee,
                etat,
                comment
            });

            const response = await fetch("/demandes", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-Token": csrfToken,
                },
                body: JSON.stringify({
                    date_demande,
                    date_debut,
                    date_fin,
                    nbr_jours,
                    annee,
                    etat,
                    comment,
                    user_id: userId
                })
            });
            
            const data = await response.json();
            console.log('Response:', data);
            
            if (response.ok) {
                setMessage({ type: "success", text: data.message || "Demande ajoutée avec succès" });
                setDateDemande(formattedToday);
                setDateDebut("");
                setDateFin("");
                setNbJours(0);
                setAnnee("");
                setEtat("en attente");
                setComment("");
                
                // Close modal and refresh page after a short delay
                setTimeout(() => {
                    onClose();
                    window.location.reload();
                }, 1500);
            } else {
                console.error('Error response:', data);
                if (data.errors) {
                    setErrors(data.errors);
                    setMessage({ type: "error", text: "Veuillez corriger les erreurs dans le formulaire" });
                } else {
                    setMessage({ type: "error", text: data.message || "Échec de l'ajout de la demande" });
                }
            }
        } catch (error) {
            console.error("Error adding demande:", error);
            setMessage({ type: "error", text: "Une erreur inattendue s'est produite" });
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter une demande</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-3">
                    {message && (
                        <div className={`p-2 rounded ${message.type === "success" ? "bg-green-200 text-green-800" : "bg-red-200 text-red-800"}`}>
                            {message.text}
                        </div>
                    )}
                    
                    <div className="space-y-1">
                        <label htmlFor="date_demande" className="text-sm font-medium">Date de demande</label>
                        <Input 
                            id="date_demande"
                            type="date" 
                            value={date_demande} 
                            onChange={(e) => setDateDemande(e.target.value)} 
                            className={errors.date_demande ? "border-red-500" : ""}
                        />
                        {errors.date_demande && (
                            <p className="text-red-500 text-xs">{errors.date_demande}</p>
                        )}
                    </div>
                    
                    <div className="space-y-1">
                        <label htmlFor="date_debut" className="text-sm font-medium">Date de début</label>
                        <Input 
                            id="date_debut"
                            type="date" 
                            value={date_debut} 
                            onChange={(e) => setDateDebut(e.target.value)} 
                            className={errors.date_debut ? "border-red-500" : ""}
                        />
                        {errors.date_debut && (
                            <p className="text-red-500 text-xs">{errors.date_debut}</p>
                        )}
                    </div>
                    
                    <div className="space-y-1">
                        <label htmlFor="date_fin" className="text-sm font-medium">Date de fin</label>
                        <Input 
                            id="date_fin"
                            type="date" 
                            value={date_fin} 
                            onChange={(e) => setDateFin(e.target.value)} 
                            className={errors.date_fin ? "border-red-500" : ""}
                        />
                        {errors.date_fin && (
                            <p className="text-red-500 text-xs">{errors.date_fin}</p>
                        )}
                    </div>
                    
                    <div className="space-y-1">
                        <label htmlFor="nbr_jours" className="text-sm font-medium">Nombre de jours</label>
                        <Input 
                            id="nbr_jours"
                            type="number" 
                            value={nbr_jours} 
                            onChange={(e) => setNbJours(parseInt(e.target.value) || 0)} 
                            className={errors.nbr_jours ? "border-red-500" : ""}
                            readOnly
                        />
                        {errors.nbr_jours && (
                            <p className="text-red-500 text-xs">{errors.nbr_jours}</p>
                        )}
                        <p className="text-gray-500 text-xs">Calculated automatically from dates</p>
                    </div>
                    
                    <div className="space-y-1">
                        <label htmlFor="annee" className="text-sm font-medium">Année</label>
                        <Input 
                            id="annee"
                            type="text" 
                            value={annee} 
                            onChange={(e) => setAnnee(e.target.value)} 
                            className={errors.annee ? "border-red-500" : ""}
                            readOnly
                        />
                        {errors.annee && (
                            <p className="text-red-500 text-xs">{errors.annee}</p>
                        )}
                        <p className="text-gray-500 text-xs">Based on start date</p>
                    </div>
                    
                    <div className="space-y-1">
                        <label htmlFor="comment" className="text-sm font-medium">Commentaire</label>
                        <Textarea 
                            id="comment"
                            value={comment}
                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setComment(e.target.value)}
                            placeholder="Entrez la raison de votre demande..."
                            className="min-h-[100px]"
                        />
                    </div>
                    
                    <div className="space-y-1">
                        <label htmlFor="etat" className="text-sm font-medium">État</label>
                        <select 
                            id="etat"
                            value={etat} 
                            onChange={(e) => setEtat(e.target.value)} 
                            className={`w-full p-2 border rounded ${errors.etat ? "border-red-500" : ""}`}
                        >
                            <option value="en attente">En attente</option>
                            <option value="acceptée">Acceptée</option>
                            <option value="refusée">Refusée</option>
                        </select>
                        {errors.etat && (
                            <p className="text-red-500 text-xs">{errors.etat}</p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={loading}>
                            {loading ? "Ajout en cours..." : "Ajouter"}
                        </Button>
                        <Button variant="outline" type="button" onClick={onClose} disabled={loading}>
                            Annuler
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
};

// Also add a default export for backward compatibility
export default AddDemandeModal;