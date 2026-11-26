import { TodayState } from '@/components/attendance/attendance';
import { Button } from '@/components/ui/button';
import { Link, router } from '@inertiajs/react';
import { Clock, LogIn, LogOut } from 'lucide-react';
import { useEffect, useState } from 'react';

/** "Pointer l'arrivée / le départ" on the employee dashboard. The server takes the time. */
export default function ClockCard({ state }: { state: TodayState }) {
    const [now, setNow] = useState(() => new Date());
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        const timer = window.setInterval(() => setNow(new Date()), 30_000);
        return () => window.clearInterval(timer);
    }, []);

    if (!state.tracking) return null;

    const post = (url: string) => router.post(url, {}, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    const time = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

    let status: string;
    let action: React.ReactNode = null;
    if (!state.clock_in) {
        status = state.working_day
            ? "Vous n'avez pas encore pointé votre arrivée."
            : "Jour de repos : pointez seulement si vous travaillez aujourd'hui.";
        action = (
            <Button onClick={() => post('/pointage/arrivee')} disabled={busy}>
                <LogIn className="mr-2 h-4 w-4" /> Pointer l'arrivée
            </Button>
        );
    } else if (!state.clock_out) {
        status = `Arrivée pointée à ${state.clock_in}.`;
        action = (
            <Button variant="outline" onClick={() => post('/pointage/depart')} disabled={busy}>
                <LogOut className="mr-2 h-4 w-4" /> Pointer le départ
            </Button>
        );
    } else {
        status = `Arrivée à ${state.clock_in}, départ à ${state.clock_out}. Bonne soirée !`;
    }

    return (
        <section
            aria-label="Pointage"
            className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div className="flex items-center gap-3">
                <Clock className="text-muted-foreground h-5 w-5 shrink-0" />
                <div>
                    <p className="font-semibold tabular-nums">Pointage · {time}</p>
                    <p className="text-muted-foreground text-sm">{status}</p>
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {action}
                <Button variant="ghost" size="sm" asChild>
                    <Link href="/pointage">Mes pointages</Link>
                </Button>
            </div>
        </section>
    );
}
