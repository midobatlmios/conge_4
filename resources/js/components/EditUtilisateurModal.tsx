import { useState, useEffect } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { User } from "@/components/users/columns";
import axios from "axios";

interface EditUtilisateurModalProps {
    isOpen: boolean;
    onClose: () => void;
    user: User | null;
    onUpdate: (updatedUser: User) => void;
}

const EditUtilisateurModal: React.FC<EditUtilisateurModalProps> = ({ isOpen, onClose, user, onUpdate }) => {
    const [formData, setFormData] = useState<User & { password?: string; password_confirmation?: string }>({
        id: 0,
        name: "",
        email: "",
        email_verified_at: null,
        role: "user",
        created_at: "",
        updated_at: "",
        password: "",
        password_confirmation: ""
    });
    
    const [message, setMessage] = useState<{type: "success" | "error" | "default"; text: string} | null>(null);
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [showPassword, setShowPassword] = useState(false);

    useEffect(() => {
        if(user) {
            setFormData({...user, password: "", password_confirmation: ""});
        }
    }, [user]);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        const {name, value} = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: value,
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

        // Vérifier que les mots de passe correspondent
        if (formData.password && formData.password !== formData.password_confirmation) {
            setErrors({
                password_confirmation: ["Les mots de passe ne correspondent pas"]
            });
            setLoading(false);
            return;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            // Ne pas envoyer le mot de passe s'il est vide
            const dataToSend = {
                ...formData,
                password: formData.password || undefined,
                password_confirmation: formData.password_confirmation || undefined
            };
            
            const response = await axios.put(`/users/${user?.id}`, dataToSend, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                withCredentials: true
            });
            
            setMessage({type: "success", text: "Utilisateur mis à jour avec succès"});
            onUpdate(response.data);
            setTimeout(() => {
                onClose();
            }, 1500);
            
        } catch (error) {
            console.error("Error updating user:", error);
            
            if (axios.isAxiosError(error) && error.response) {
                if (error.response.status === 422 && error.response.data.errors) {
                    setErrors(error.response.data.errors);
                    const errorMessage = Object.values(error.response.data.errors).flat().join(', ');
                    setMessage({type: "error", text: errorMessage});
                } else {
                    setMessage({
                        type: "error", 
                        text: error.response.data.message || "Une erreur s'est produite lors de la mise à jour de l'utilisateur"
                    });
                }
            } else {
                setMessage({type: "error", text: "Une erreur s'est produite lors de la mise à jour de l'utilisateur"});
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifier l'utilisateur</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {message && ( 
                        <div className={`p-2 rounded ${message.type === "success" ? "bg-green-200 text-green-800" : "bg-red-200 text-red-800"}`}>
                            {message.text}
                        </div>
                    )}
                    
                    <div className="flex flex-col space-y-1">
                        <label htmlFor="name">Nom</label>
                        <Input 
                            id="name"
                            type="text" 
                            name="name" 
                            value={formData.name} 
                            onChange={handleChange} 
                            className={`w-full ${errors.name ? 'border-red-500' : ''}`}
                            required 
                        />
                        {errors.name && <p className="text-red-500 text-sm">{errors.name[0]}</p>}
                    </div>
                    
                    <div className="flex flex-col space-y-1">
                        <label htmlFor="email">Email</label>
                        <Input 
                            id="email"
                            type="email" 
                            name="email" 
                            value={formData.email} 
                            onChange={handleChange} 
                            className={`w-full ${errors.email ? 'border-red-500' : ''}`}
                            required 
                        />
                        {errors.email && <p className="text-red-500 text-sm">{errors.email[0]}</p>}
                    </div>

                    <div className="space-y-4 border-t pt-4">
                        <h3 className="font-medium">Changer le mot de passe</h3>
                        <div className="flex flex-col space-y-1">
                            <label htmlFor="password">Nouveau mot de passe</label>
                            <div className="relative">
                                <Input 
                                    id="password"
                                    type={showPassword ? "text" : "password"}
                                    name="password" 
                                    value={formData.password} 
                                    onChange={handleChange} 
                                    className={`w-full ${errors.password ? 'border-red-500' : ''}`}
                                    minLength={8}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="absolute right-2 top-1/2 -translate-y-1/2"
                                    onClick={() => setShowPassword(!showPassword)}
                                >
                                    {showPassword ? "Masquer" : "Afficher"}
                                </Button>
                            </div>
                            {errors.password && <p className="text-red-500 text-sm">{errors.password[0]}</p>}
                        </div>

                        <div className="flex flex-col space-y-1">
                            <label htmlFor="password_confirmation">Confirmer le mot de passe</label>
                            <Input 
                                id="password_confirmation"
                                type={showPassword ? "text" : "password"}
                                name="password_confirmation" 
                                value={formData.password_confirmation} 
                                onChange={handleChange} 
                                className={`w-full ${errors.password_confirmation ? 'border-red-500' : ''}`}
                                minLength={8}
                            />
                            {errors.password_confirmation && <p className="text-red-500 text-sm">{errors.password_confirmation[0]}</p>}
                        </div>
                    </div>
                    
                    <div className="flex flex-col space-y-1">
                        <label htmlFor="role">Rôle</label>
                        <select 
                            id="role"
                            name="role" 
                            value={formData.role} 
                            onChange={handleChange} 
                            className={`border p-2 rounded w-full ${errors.role ? 'border-red-500' : ''}`}
                            required
                        >
                            <option value="user">Utilisateur</option>
                            <option value="admin">Administrateur</option>
                        </select>
                        {errors.role && <p className="text-red-500 text-sm">{errors.role[0]}</p>}
                    </div>
                    
                    <DialogFooter>
                        <Button variant="outline" type="button" onClick={onClose} disabled={loading}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={loading}>
                            {loading ? "Mise à jour..." : "Modifier utilisateur"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
};

export default EditUtilisateurModal;