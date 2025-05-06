import { useState } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { User } from "@/components/users/columns";
import axios from "axios";

interface ResetPasswordModalProps {
    isOpen: boolean;
    onClose: () => void;
    user: User | null;
    onSuccess: () => void;
}

const ResetPasswordModal: React.FC<ResetPasswordModalProps> = ({ isOpen, onClose, user, onSuccess }) => {
    const [password, setPassword] = useState("");
    const [passwordConfirmation, setPasswordConfirmation] = useState("");
    const [message, setMessage] = useState<{type: "success" | "error" | "default"; text: string} | null>(null);
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [showPassword, setShowPassword] = useState(false);

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setMessage(null);
        setErrors({});

        if (password !== passwordConfirmation) {
            setErrors({
                password_confirmation: ["Les mots de passe ne correspondent pas"]
            });
            setLoading(false);
            return;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            const response = await axios.put(`/users/${user?.id}/reset-password`, {
                password,
                password_confirmation: passwordConfirmation
            }, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                withCredentials: true
            });
            
            setMessage({type: "success", text: "Mot de passe réinitialisé avec succès"});
            setTimeout(() => {
                onClose();
                onSuccess();
            }, 1500);
            
        } catch (error) {
            console.error("Error resetting password:", error);
            
            if (axios.isAxiosError(error) && error.response) {
                if (error.response.status === 422 && error.response.data.errors) {
                    setErrors(error.response.data.errors);
                    const errorMessage = Object.values(error.response.data.errors).flat().join(', ');
                    setMessage({type: "error", text: errorMessage});
                } else {
                    setMessage({
                        type: "error", 
                        text: error.response.data.message || "Une erreur s'est produite lors de la réinitialisation du mot de passe"
                    });
                }
            } else {
                setMessage({type: "error", text: "Une erreur s'est produite lors de la réinitialisation du mot de passe"});
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Réinitialiser le mot de passe de {user?.name}</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {message && ( 
                        <div className={`p-2 rounded ${message.type === "success" ? "bg-green-200 text-green-800" : "bg-red-200 text-red-800"}`}>
                            {message.text}
                        </div>
                    )}
                    
                    <div className="flex flex-col space-y-1">
                        <label htmlFor="password">Nouveau mot de passe</label>
                        <div className="relative">
                            <Input 
                                id="password"
                                type={showPassword ? "text" : "password"}
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className={`w-full ${errors.password ? 'border-red-500' : ''}`}
                                minLength={8}
                                required
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
                            value={passwordConfirmation}
                            onChange={(e) => setPasswordConfirmation(e.target.value)}
                            className={`w-full ${errors.password_confirmation ? 'border-red-500' : ''}`}
                            minLength={8}
                            required
                        />
                        {errors.password_confirmation && <p className="text-red-500 text-sm">{errors.password_confirmation[0]}</p>}
                    </div>
                    
                    <DialogFooter>
                        <Button variant="outline" type="button" onClick={onClose} disabled={loading}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={loading}>
                            {loading ? "Réinitialisation..." : "Réinitialiser le mot de passe"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
};

export default ResetPasswordModal; 