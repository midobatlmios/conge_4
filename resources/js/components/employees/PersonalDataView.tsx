import { displayValue, EmployeeProfile, SECTIONS } from '@/components/employees/personal-data';
import { t } from '@/lib/i18n';

/** Read-only view of personal data (the employee's own, in Paramètres). */
export default function PersonalDataView({ profile }: { profile: EmployeeProfile }) {
    return (
        <div className="space-y-6">
            {SECTIONS.map((section) => (
                <section key={section.title} className="space-y-2">
                    <h3 className="text-muted-foreground text-sm font-semibold tracking-wide uppercase">{section.title}</h3>
                    <dl className="divide-y rounded-md border">
                        {section.fields.map(({ field, label }) => {
                            const value = displayValue(profile, field);
                            return (
                                <div key={field} className="grid gap-1 px-4 py-2 text-sm sm:grid-cols-3">
                                    <dt className="text-muted-foreground">{label}</dt>
                                    <dd className={`sm:col-span-2 ${field === 'rib' ? 'font-mono' : ''} ${value ? '' : 'text-muted-foreground'}`}>
                                        {value || t('Non renseigné')}
                                    </dd>
                                </div>
                            );
                        })}
                    </dl>
                </section>
            ))}
        </div>
    );
}
