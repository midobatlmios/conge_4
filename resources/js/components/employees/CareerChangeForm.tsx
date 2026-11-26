import { money } from '@/components/employees/career';
import { Contract, Option as ContractOption } from '@/components/employees/contracts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Option } from '@/components/users/EmployeeRecordFields';
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

interface CareerChangeFormProps {
    employeeId: number;
    /** Current situation, shown next to each field. */
    current: { jobTitle: string | null; departmentId: number | null; contract: Contract | null };
    kinds: ContractOption[];
    categories: ContractOption[];
    departments: Option[];
    onDone: () => void;
}

/**
 * HR records a promotion, raise or transfer. Fields left empty do not change;
 * the change applies at once to the record and the current contract.
 */
export default function CareerChangeForm({ employeeId, current, kinds, categories, departments, onDone }: CareerChangeFormProps) {
    const today = new Date().toLocaleDateString('sv-SE');
    const form = useForm({
        kind: 'promotion',
        effective_date: today,
        job_title: '',
        change_department: false as boolean,
        department_id: current.departmentId === null ? '' : String(current.departmentId),
        category: '',
        gross_salary: '',
        notes: '',
    });
    const { data, setData, errors, processing } = form;
    const contract = current.contract;
    const currentDepartment = departments.find((d) => d.id === current.departmentId)?.name;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/admin/users/${employeeId}/career-changes`, { preserveScroll: true, onSuccess: onDone });
    };

    const error = (field: keyof typeof errors) => errors[field] && <p className="text-sm text-red-700 dark:text-red-400">{errors[field]}</p>;
    const now = (text: string) => <p className="text-muted-foreground text-xs">Actuellement : {text}</p>;

    return (
        <form onSubmit={submit} className="space-y-4 rounded-md border border-dashed p-4">
            <div>
                <h3 className="text-sm font-semibold">Nouveau changement</h3>
                <p className="text-muted-foreground text-xs">
                    Remplissez seulement ce qui change. Le dossier et le contrat en cours sont mis à jour ; l'ancienne situation reste dans
                    l'historique.
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1">
                    <Label htmlFor="career-kind">Type de changement</Label>
                    <select id="career-kind" className={selectClass} value={data.kind} onChange={(e) => setData('kind', e.target.value)}>
                        {kinds.map((kind) => (
                            <option key={kind.value} value={kind.value}>
                                {kind.label}
                            </option>
                        ))}
                    </select>
                    {error('kind')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="career-date">Date d'effet</Label>
                    <Input
                        id="career-date"
                        type="date"
                        max={today}
                        value={data.effective_date}
                        onChange={(e) => setData('effective_date', e.target.value)}
                        required
                    />
                    {error('effective_date')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="career-job">Nouveau poste</Label>
                    <Input id="career-job" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                    {now(current.jobTitle || 'non renseigné')}
                    {error('job_title')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="career-department">Nouveau département</Label>
                    <select
                        id="career-department"
                        className={selectClass}
                        value={data.change_department ? data.department_id : 'same'}
                        onChange={(e) =>
                            setData((d) => ({
                                ...d,
                                change_department: e.target.value !== 'same',
                                department_id: e.target.value === 'same' ? d.department_id : e.target.value,
                            }))
                        }
                    >
                        <option value="same">Inchangé</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                        <option value="">Aucun département</option>
                    </select>
                    {now(currentDepartment ?? 'aucun')}
                    {error('department_id')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="career-category">Nouvelle catégorie</Label>
                    <select
                        id="career-category"
                        className={selectClass}
                        value={data.category}
                        onChange={(e) => setData('category', e.target.value)}
                        disabled={!contract}
                    >
                        <option value="">Inchangée</option>
                        {categories.map((category) => (
                            <option key={category.value} value={category.value}>
                                {category.label}
                            </option>
                        ))}
                    </select>
                    {contract ? now(contract.category_label ?? 'non renseignée') : now('pas de contrat en cours')}
                    {error('category')}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="career-salary">Nouveau salaire brut mensuel (DH)</Label>
                    <Input
                        id="career-salary"
                        type="number"
                        min={0}
                        step="0.01"
                        value={data.gross_salary}
                        onChange={(e) => setData('gross_salary', e.target.value)}
                        disabled={!contract}
                    />
                    {contract ? now(contract.gross_salary === null ? 'non renseigné' : money(contract.gross_salary)) : now('pas de contrat en cours')}
                    {error('gross_salary')}
                </div>
                <div className="space-y-1 sm:col-span-2">
                    <Label htmlFor="career-notes">Remarques (facultatif)</Label>
                    <Textarea
                        id="career-notes"
                        rows={2}
                        placeholder="Avenant signé le…, entretien annuel…"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                    {error('notes')}
                </div>
            </div>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onDone} disabled={processing}>
                    Annuler
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing ? 'Enregistrement…' : 'Enregistrer et appliquer'}
                </Button>
            </div>
        </form>
    );
}
