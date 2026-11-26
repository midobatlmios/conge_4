import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export type Option = { id: number; name: string };

/** Employee record part of the user forms. Ids are strings here; '' means none. */
export type EmployeeRecord = {
    matricule: string;
    department_id: string;
    job_title: string;
    hire_date: string;
    manager_id: string;
};

export const emptyEmployeeRecord: EmployeeRecord = { matricule: '', department_id: '', job_title: '', hire_date: '', manager_id: '' };

// Radix Select cannot use '' as an item value.
const NONE = 'none';

interface EmployeeRecordFieldsProps {
    data: EmployeeRecord;
    setData: (field: keyof EmployeeRecord, value: string) => void;
    errors: Partial<Record<keyof EmployeeRecord, string>>;
    departments: Option[];
    managers: Option[];
    /** The employee being edited, who cannot be their own manager. */
    selfId?: number;
}

export default function EmployeeRecordFields({ data, setData, errors, departments, managers, selfId }: EmployeeRecordFieldsProps) {
    const error = (field: keyof EmployeeRecord) => errors[field] && <p className="text-sm text-red-700 dark:text-red-400">{errors[field]}</p>;

    return (
        <div className="grid gap-4 border-t pt-4 sm:grid-cols-2">
            <h3 className="font-medium sm:col-span-2">Dossier employé</h3>

            <div className="space-y-1">
                <Label htmlFor="matricule">Matricule</Label>
                <Input id="matricule" value={data.matricule} onChange={(e) => setData('matricule', e.target.value)} />
                {error('matricule')}
            </div>

            <div className="space-y-1">
                <Label htmlFor="hire_date">Date d'embauche</Label>
                <Input
                    id="hire_date"
                    type="date"
                    value={data.hire_date}
                    onChange={(e) => setData('hire_date', e.target.value)}
                    className={errors.hire_date ? 'border-red-500' : ''}
                    required
                />
                {error('hire_date')}
            </div>

            <div className="space-y-1">
                <Label htmlFor="job_title">Poste</Label>
                <Input id="job_title" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} placeholder="Ex. : Comptable" />
                {error('job_title')}
            </div>

            <div className="space-y-1">
                <Label htmlFor="department_id">Département</Label>
                <Select value={data.department_id || NONE} onValueChange={(value) => setData('department_id', value === NONE ? '' : value)}>
                    <SelectTrigger id="department_id">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>Aucun</SelectItem>
                        {departments.map((d) => (
                            <SelectItem key={d.id} value={String(d.id)}>
                                {d.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {departments.length === 0 && <p className="text-muted-foreground text-xs">Créez d'abord des départements dans « Départements ».</p>}
                {error('department_id')}
            </div>

            <div className="space-y-1 sm:col-span-2">
                <Label htmlFor="manager_id">Responsable</Label>
                <Select value={data.manager_id || NONE} onValueChange={(value) => setData('manager_id', value === NONE ? '' : value)}>
                    <SelectTrigger id="manager_id" className={errors.manager_id ? 'border-red-500' : ''}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>Aucun</SelectItem>
                        {managers
                            .filter((m) => m.id !== selfId)
                            .map((m) => (
                                <SelectItem key={m.id} value={String(m.id)}>
                                    {m.name}
                                </SelectItem>
                            ))}
                    </SelectContent>
                </Select>
                {error('manager_id')}
            </div>
        </div>
    );
}
