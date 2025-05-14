import { useState, useEffect } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { router } from '@inertiajs/react';
import axios from 'axios';

export const AddDemandeModal = ({ isOpen, onClose, isAdmin = false, users = [] }: { isOpen: boolean, onClose: () => void, isAdmin?: boolean, users?: { id: number; name: string }[] }) => {
    // Format today's date as YYYY-MM-DD for the default date_demande value
    const today = new Date();
    const formattedToday = today.toISOString().split('T')[0];
    
    // Get user ID from meta tag
    const userId = document.querySelector('meta[name="user-id"]')?.getAttribute("content");
    
    const [selectedUserId, setSelectedUserId] = useState<string>("");
    const [date_demande, setDateDemande] = useState(formattedToday);
    const [date_debut, setDateDebut] = useState("");
    const [date_fin, setDateFin] = useState("");
    const [nbr_jours, setNbJours] = useState(0);
    const [annee, setAnnee] = useState("");
    const [type_conge, setTypeConge] = useState<"mariage" | "naissance" | "deces" | "sans_solde" | "recuperation">("mariage");
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
    
    // Add useEffect to handle type_conge changes
    useEffect(() => {
        // Reset dates when type_conge changes
        setDateDebut("");
        setDateFin("");
        setNbJours(0);
        setAnnee("");
        setComment("");

        // Set default dates based on type_conge
        const today = new Date();
        const formattedToday = today.toISOString().split('T')[0];
        setDateDemande(formattedToday);

        let startDate: Date;
        let endDate: Date;

        switch (type_conge) {
            case "mariage":
                // Set default dates for mariage (4 days)
                startDate = new Date();
                startDate.setDate(startDate.getDate() + 1); // Start tomorrow
                endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 3); // 4 days total
                setDateDebut(startDate.toISOString().split('T')[0]);
                setDateFin(endDate.toISOString().split('T')[0]);
                break;
            case "naissance":
                // Set default dates for naissance (3 days)
                startDate = new Date();
                startDate.setDate(startDate.getDate() + 1);
                endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 2); // 3 days total
                setDateDebut(startDate.toISOString().split('T')[0]);
                setDateFin(endDate.toISOString().split('T')[0]);
                break;
            case "deces":
                // Set default dates for deces (3 days)
                startDate = new Date();
                startDate.setDate(startDate.getDate() + 1);
                endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 2); // 3 days total
                setDateDebut(startDate.toISOString().split('T')[0]);
                setDateFin(endDate.toISOString().split('T')[0]);
                break;
            case "recuperation":
                // For recuperation, we don't set default dates as they depend on the dates to recover
                setComment("Veuillez préciser les dates à récupérer");
                break;
            case "sans_solde":
                // For sans_solde, we don't set default dates
                break;
        }
    }, [type_conge]);
    
    // Add useEffect to handle date_debut changes
    useEffect(() => {
        if (date_debut) {
            const startDate = new Date(date_debut);
            const endDate = new Date(startDate);

            // Set end date based on type_conge
            switch (type_conge) {
                case "mariage":
                    endDate.setDate(endDate.getDate() + 2); // 4 days total (including start date)
                    break;
                case "naissance":
                    endDate.setDate(endDate.getDate() + 1); // 3 days total
                    break;
                case "deces":
                    endDate.setDate(endDate.getDate() + 1); // 3 days total
                    break;
                default:
                    // For other types, don't automatically set end date
                    return;
            }

            setDateFin(endDate.toISOString().split('T')[0]);
        }
    }, [date_debut, type_conge]);

    // Update the date_debut handler
    const handleDateDebutChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newDateDebut = e.target.value;
        setDateDebut(newDateDebut);

        if (newDateDebut) {
            const startDate = new Date(newDateDebut);
            const endDate = new Date(startDate);

            // Set end date based on type_conge
            switch (type_conge) {
                case "mariage":
                    endDate.setDate(endDate.getDate() + 2); // 4 days total
                    break;
                case "naissance":
                    endDate.setDate(endDate.getDate() + 1); // 3 days total
                    break;
                case "deces":
                    endDate.setDate(endDate.getDate() + 1); // 3 days total
                    break;
                default:
                    // For other types, don't automatically set end date
                    return;
            }

            setDateFin(endDate.toISOString().split('T')[0]);
        }
    };

    // Validate form with type-specific rules
    const validateForm = () => {
        const newErrors: Record<string, string> = {};
        
        if (isAdmin && !selectedUserId) {
            newErrors.user_id = "Veuillez sélectionner un employé";
        }
        
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

            // Type-specific validations
            switch (type_conge) {
                case "mariage":
                    if (nbr_jours > 4) {
                        newErrors.nbr_jours = "Vous ne pouvez pas renseigner plus de 4 jours pour ce type de congé";
                    }
                    break;
                case "naissance": {
                    const birthDate = new Date(date_debut);
                    const oneMonthLater = new Date(birthDate);
                    oneMonthLater.setMonth(oneMonthLater.getMonth() + 1);
                    if (endDate > oneMonthLater) {
                        newErrors.date_fin = "Le congé de naissance doit être pris dans un délai d'un mois";
                    }
                    break;
                }
                case "deces":
                    if (nbr_jours > 3) {
                        newErrors.nbr_jours = "Vous ne pouvez pas renseigner plus de 3 jours pour ce type de congé";
                    }
                    break;
                case "recuperation":
                    if (!comment) {
                        newErrors.comment = "Veuillez renseigner les dates sujet de récupération";
                    }
                    break;
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
        
        try {
            const formData = {
                date_demande,
                date_debut,
                date_fin,
                nbr_jours,
                annee,
                type_conge,
                etat,
                comment,
                user_id: isAdmin ? selectedUserId : userId
            };

            // Use axios with proper CSRF token handling
            const response = await axios.post('/demandes', formData, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                withCredentials: true
            });

            if (response.data) {
                setMessage({ type: "success", text: "Demande ajoutée avec succès" });
                setDateDemande(formattedToday);
                setDateDebut("");
                setDateFin("");
                setNbJours(0);
                setAnnee("");
                setTypeConge("mariage");
                setEtat("en attente");
                setComment("");
                
                setTimeout(() => {
                    onClose();
                    router.reload();
                }, 1500);
            }
        } catch (error) {
            console.error("Error adding demande:", error);
            if (axios.isAxiosError(error)) {
                if (error.response?.status === 419) {
                    setMessage({ type: "error", text: "Session expirée. Veuillez rafraîchir la page et réessayer." });
                } else if (error.response?.data?.errors) {
                    setErrors(error.response.data.errors);
                    setMessage({ type: "error", text: "Veuillez corriger les erreurs dans le formulaire" });
                } else {
                    setMessage({ type: "error", text: error.response?.data?.message || "Une erreur s'est produite lors de l'ajout de la demande" });
                }
            } else {
                setMessage({ type: "error", text: "Une erreur inattendue s'est produite" });
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] flex flex-col w-[95vw] sm:w-[85vw] md:w-[75vw] lg:w-[60vw] xl:w-[50vw]">
                <DialogHeader>
                    <DialogTitle>Ajouter une demande de congé</DialogTitle>
                    <DialogDescription>
                        Remplissez le formulaire ci-dessous pour soumettre une nouvelle demande de congé.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex-1 overflow-y-auto pr-4 -mr-4">
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {message && ( 
                            <div className={`p-2 rounded ${message.type === "success" ? "bg-green-200 text-green-800" : "bg-red-200 text-red-800"}`}>
                                {message.text}
                            </div>
                        )}
                        
                        {isAdmin && (
                            <div className="space-y-1">
                                <label htmlFor="user_id" className="text-sm font-medium">Employé</label>
                                <Select
                                    value={selectedUserId}
                                    onValueChange={setSelectedUserId}
                                >
                                    <SelectTrigger className={errors.user_id ? "border-red-500" : ""}>
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
                                {errors.user_id && (
                                    <p className="text-red-500 text-xs">{errors.user_id}</p>
                                )}
                            </div>
                        )}

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label htmlFor="type_conge" className="text-sm font-medium">Type de congé</label>
                                <Select
                                    value={type_conge}
                                    onValueChange={(value: "mariage" | "naissance" | "deces" | "sans_solde" | "recuperation") => {
                                        setTypeConge(value);
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
                                {type_conge === "mariage" && (
                                    <p className="text-sm text-gray-500">4 jours dont deux jours payés et deux jours déduits automatiquement du congé</p>
                                )}
                                {type_conge === "naissance" && (
                                    <p className="text-sm text-gray-500">3 jours - à prendre dans un délai de un mois à compter de la date de la naissance</p>
                                )}
                                {type_conge === "deces" && (
                                    <p className="text-sm text-gray-500">3 jours (décès conjoint, d'un enfant, d'un petit-enfant, d'un ascendant), dont un jour payé et deux jours déduits automatiquement du congé</p>
                                )}
                                {type_conge === "sans_solde" && (
                                    <p className="text-sm text-gray-500">Ce congé sera déduit automatiquement de votre salaire</p>
                                )}
                                {type_conge === "recuperation" && (
                                    <p className="text-sm text-gray-500">La journée à récupérer doit se faire dans un délai de 2 mois</p>
                                )}
                            </div>
                            
                            <div className="space-y-1">
                                <label htmlFor="nbr_jours" className="text-sm font-medium">Nombre de jours</label>
                                <Input 
                                    id="nbr_jours"
                                    type="number" 
                                    name="nbr_jours" 
                                    value={nbr_jours} 
                                    className="w-full"
                                    readOnly 
                                />
                                <p className="text-gray-500 text-xs">Calculé automatiquement à partir des dates</p>
                            </div>
                        </div>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label htmlFor="date_debut" className="text-sm font-medium">Date de début</label>
                                <Input 
                                    id="date_debut"
                                    type="date" 
                                    name="date_debut" 
                                    value={date_debut} 
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
                                    value={date_fin} 
                                    onChange={(e) => setDateFin(e.target.value)} 
                                    className={errors.date_fin ? "border-red-500" : ""}
                                    required 
                                    readOnly={type_conge === "mariage" || type_conge === "naissance" || type_conge === "deces"}
                                />
                                {errors.date_fin && <p className="text-red-500 text-xs">{errors.date_fin[0]}</p>}
                                {(type_conge === "mariage" || type_conge === "naissance" || type_conge === "deces") && (
                                    <p className="text-sm text-gray-500">La date de fin est automatiquement calculée en fonction de la date de début</p>
                                )}
                            </div>
                        </div>
                        
                        <div className="space-y-1">
                            <label htmlFor="comment" className="text-sm font-medium">Commentaire</label>
                            <Textarea 
                                id="comment"
                                name="comment"
                                value={comment || ''}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setComment(e.target.value)}
                                placeholder="Entrez la raison de votre demande..."
                                className={`min-h-[100px] ${errors.comment ? 'border-red-500' : ''}`}
                            />
                            {errors.comment && <p className="text-red-500 text-xs">{errors.comment[0]}</p>}
                        </div>
                    </form>
                </div>

                <DialogFooter className="mt-4 flex-col sm:flex-row gap-2">
                    <Button variant="outline" type="button" onClick={onClose} disabled={loading} className="w-full sm:w-auto">
                        Annuler
                    </Button>
                    <Button type="submit" disabled={loading} onClick={handleSubmit} className="w-full sm:w-auto">
                        {loading ? "Envoi..." : "Soumettre la demande"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

// Also add a default export for backward compatibility
export default AddDemandeModal;