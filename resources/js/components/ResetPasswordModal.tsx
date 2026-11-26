import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { User } from '@/components/users/columns';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

interface ResetPasswordModalProps {
    isOpen: boolean;
    onClose: () => void;
    user: User | null;
    onSuccess: () => void;
}

const ResetPasswordModal: React.FC<ResetPasswordModalProps> = ({ isOpen, onClose, user, onSuccess }) => {
    const { data, setData, put, processing, errors, setError, clearErrors, wasSuccessful } = useForm({
        password: '',
        password_confirmation: '',
    });
    const [showPassword, setShowPassword] = useState(false);

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        clearErrors();

        if (data.password !== data.password_confirmation) {
            setError('password_confirmation', 'Les mots de passe ne correspondent pas');
            return;
        }

        put(`/admin/users/${user?.id}/reset-password`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setTimeout(onSuccess, 1500),
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Réinitialiser le mot de passe de {user?.name}</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {wasSuccessful && <div className="rounded bg-green-200 p-2 text-green-800">Mot de passe réinitialisé avec succès</div>}

                    <div className="flex flex-col space-y-1">
                        <label htmlFor="password">Nouveau mot de passe</label>
                        <div className="relative">
                            <Input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className={`w-full ${errors.password ? 'border-red-500' : ''}`}
                                minLength={8}
                                required
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="absolute end-2 top-1/2 -translate-y-1/2"
                                onClick={() => setShowPassword(!showPassword)}
                            >
                                {showPassword ? 'Masquer' : 'Afficher'}
                            </Button>
                        </div>
                        {errors.password && <p className="text-sm text-red-700 dark:text-red-400">{errors.password}</p>}
                    </div>

                    <div className="flex flex-col space-y-1">
                        <label htmlFor="password_confirmation">Confirmer le mot de passe</label>
                        <Input
                            id="password_confirmation"
                            type={showPassword ? 'text' : 'password'}
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className={`w-full ${errors.password_confirmation ? 'border-red-500' : ''}`}
                            minLength={8}
                            required
                        />
                        {errors.password_confirmation && <p className="text-sm text-red-700 dark:text-red-400">{errors.password_confirmation}</p>}
                    </div>

                    <DialogFooter>
                        <Button variant="outline" type="button" onClick={onClose} disabled={processing}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Réinitialisation...' : 'Réinitialiser le mot de passe'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
};

export default ResetPasswordModal;
