import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import EmployeeRecordFields, { emptyEmployeeRecord, Option } from '@/components/users/EmployeeRecordFields';
import { t } from '@/lib/i18n';
import { RoleOption } from '@/lib/roles';
import { useForm } from '@inertiajs/react';

interface AddUtilisateurModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
    departments: Option[];
    managers: Option[];
    /** Roles the signed-in user may give (RH: employees only). */
    roles: RoleOption[];
}

export default function AddUtilisateurModal({ isOpen, onClose, onSuccess, departments, managers, roles }: AddUtilisateurModalProps) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
        role: 'user',
        ...emptyEmployeeRecord,
    });

    const close = () => {
        reset();
        clearErrors();
        onClose();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/users', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reset();
                onSuccess();
            },
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={close}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[560px]">
                <DialogHeader>
                    <DialogTitle>{t('Ajouter un utilisateur')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="name">{t('Nom')}</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        {errors.name && <p className="text-sm text-red-700 dark:text-red-400">{errors.name}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="email">{t('Adresse e-mail')}</Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                        {errors.email && <p className="text-sm text-red-700 dark:text-red-400">{errors.email}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="password">{t('Mot de passe')}</Label>
                        <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} required />
                        {errors.password && <p className="text-sm text-red-700 dark:text-red-400">{errors.password}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="role">{t('Rôle')}</Label>
                        <Select value={data.role} onValueChange={(value) => setData('role', value)} disabled={roles.length < 2}>
                            <SelectTrigger id="role">
                                <SelectValue placeholder={t('Sélectionner un rôle')} />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role.value} value={role.value}>
                                        {role.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.role && <p className="text-sm text-red-700 dark:text-red-400">{errors.role}</p>}
                    </div>
                    <EmployeeRecordFields data={data} setData={setData} errors={errors} departments={departments} managers={managers} />
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={close} disabled={processing}>
                            {t('Annuler')}
                        </Button>
                        <Button type="submit" disabled={processing} className="bg-green-700 text-white hover:bg-green-800">
                            {processing ? t('Ajout...') : t('Ajouter')}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
