import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { User } from '@/components/users/columns';
import EmployeeRecordFields, { Option } from '@/components/users/EmployeeRecordFields';
import { RoleOption } from '@/lib/roles';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

interface EditUtilisateurModalProps {
    isOpen: boolean;
    onClose: () => void;
    user: User;
    departments: Option[];
    managers: Option[];
    /** Roles the signed-in user may give (RH: employees only). */
    roles: RoleOption[];
}

const EditUtilisateurModal: React.FC<EditUtilisateurModalProps> = ({ isOpen, onClose, user, departments, managers, roles }) => {
    const { data, setData, put, processing, errors, setError, clearErrors, transform } = useForm({
        name: user.name,
        email: user.email,
        role: user.role,
        password: '',
        password_confirmation: '',
        matricule: user.matricule ?? '',
        department_id: user.department_id ? String(user.department_id) : '',
        job_title: user.job_title ?? '',
        hire_date: user.hire_date ?? '',
        manager_id: user.manager_id ? String(user.manager_id) : '',
    });

    // Keep a current manager who has since left selectable, so the form shows them.
    const managerOptions = user.manager && !managers.some((m) => m.id === user.manager?.id) ? [...managers, user.manager] : managers;
    const [showPassword, setShowPassword] = useState(false);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        const name = e.target.name as keyof typeof data;
        setData(name, e.target.value);
        clearErrors(name);
    };

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        clearErrors();

        if (data.password && data.password !== data.password_confirmation) {
            setError('password_confirmation', 'Les mots de passe ne correspondent pas');
            return;
        }

        // An empty password means "keep the current one".
        transform(({ password, password_confirmation, ...rest }) => (password ? { ...rest, password, password_confirmation } : rest));
        put(`/admin/users/${user.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onClose,
        });
    };

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent aria-describedby="edit-user-description" className="max-h-[90vh] overflow-y-auto sm:max-w-[560px]">
                <DialogHeader>
                    <DialogTitle>Modifier l'utilisateur</DialogTitle>
                </DialogHeader>
                <div id="edit-user-description" className="sr-only">
                    Formulaire de modification des informations de l'utilisateur
                </div>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {hasErrors && <div className="rounded bg-red-200 p-2 text-red-800">Veuillez corriger les champs indiqués ci-dessous.</div>}

                    <div className="flex flex-col space-y-1">
                        <label htmlFor="name">Nom</label>
                        <Input
                            id="name"
                            type="text"
                            name="name"
                            value={data.name}
                            onChange={handleChange}
                            className={`w-full ${errors.name ? 'border-red-500' : ''}`}
                            required
                        />
                        {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                    </div>

                    <div className="flex flex-col space-y-1">
                        <label htmlFor="email">Adresse e-mail</label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            onChange={handleChange}
                            className={`w-full ${errors.email ? 'border-red-500' : ''}`}
                            required
                        />
                        {errors.email && <p className="text-sm text-red-500">{errors.email}</p>}
                    </div>

                    <div className="space-y-4 border-t pt-4">
                        <h3 className="font-medium">Changer le mot de passe</h3>
                        <div className="flex flex-col space-y-1">
                            <label htmlFor="password">Nouveau mot de passe</label>
                            <div className="relative">
                                <Input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    name="password"
                                    value={data.password}
                                    onChange={handleChange}
                                    className={`w-full ${errors.password ? 'border-red-500' : ''}`}
                                    minLength={8}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="absolute top-1/2 right-2 -translate-y-1/2"
                                    onClick={() => setShowPassword(!showPassword)}
                                >
                                    {showPassword ? 'Masquer' : 'Afficher'}
                                </Button>
                            </div>
                            {errors.password && <p className="text-sm text-red-500">{errors.password}</p>}
                        </div>

                        <div className="flex flex-col space-y-1">
                            <label htmlFor="password_confirmation">Confirmer le mot de passe</label>
                            <Input
                                id="password_confirmation"
                                type={showPassword ? 'text' : 'password'}
                                name="password_confirmation"
                                value={data.password_confirmation}
                                onChange={handleChange}
                                className={`w-full ${errors.password_confirmation ? 'border-red-500' : ''}`}
                                minLength={8}
                            />
                            {errors.password_confirmation && <p className="text-sm text-red-500">{errors.password_confirmation}</p>}
                        </div>
                    </div>

                    <div className="flex flex-col space-y-1">
                        <label htmlFor="role">Rôle</label>
                        <select
                            id="role"
                            name="role"
                            value={data.role}
                            onChange={handleChange}
                            className={`w-full rounded border p-2 ${errors.role ? 'border-red-500' : ''}`}
                            required
                            disabled={roles.length < 2}
                        >
                            {roles.map((role) => (
                                <option key={role.value} value={role.value}>
                                    {role.label}
                                </option>
                            ))}
                        </select>
                        {errors.role && <p className="text-sm text-red-500">{errors.role}</p>}
                    </div>

                    <EmployeeRecordFields
                        data={data}
                        setData={(field, value) => {
                            setData(field, value);
                            clearErrors(field);
                        }}
                        errors={errors}
                        departments={departments}
                        managers={managerOptions}
                        selfId={user.id}
                    />

                    <DialogFooter>
                        <Button variant="outline" type="button" onClick={onClose} disabled={processing}>
                            Annuler
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Mise à jour...' : 'Modifier utilisateur'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
};

export default EditUtilisateurModal;
