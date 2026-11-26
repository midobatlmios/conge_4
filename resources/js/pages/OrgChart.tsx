import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { usePermissions } from '@/lib/roles';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

type Person = { id: number; name: string; job_title: string | null; department: string | null; manager_id: number | null };

type Node = Person & { reports: Node[]; /** Everyone below, at any depth. */ size: number };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Organigramme', href: '/organigramme' }];

/** "Élodie" matches "elodie": accents and case are ignored. */
const normalize = (text: string) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

function buildTree(people: Person[]): Node[] {
    const nodes = new Map<number, Node>(people.map((p) => [p.id, { ...p, reports: [], size: 0 }]));
    const roots: Node[] = [];

    for (const node of nodes.values()) {
        const manager = node.manager_id ? nodes.get(node.manager_id) : undefined;
        if (manager) manager.reports.push(node);
        else roots.push(node);
    }

    const measure = (node: Node): number => {
        // Managers first (they branch), then their team, each by name.
        node.reports.sort((a, b) => Number(b.reports.length > 0) - Number(a.reports.length > 0) || a.name.localeCompare(b.name, 'fr'));
        node.size = node.reports.reduce((total, child) => total + 1 + measure(child), 0);
        return node.size;
    };
    roots.forEach(measure);

    // The biggest tree first; people with no manager and no team are listed apart.
    return roots.sort((a, b) => b.size - a.size);
}

interface ViewProps {
    collapsed: Set<number>;
    toggle: (id: number) => void;
    matches: Set<number>;
    selfId: number;
    canOpenFiles: boolean;
    register: (id: number, element: HTMLElement | null) => void;
}

function PersonName({ person, canOpenFiles }: { person: Person; canOpenFiles: boolean }) {
    return canOpenFiles ? (
        <Link href={`/admin/users/${person.id}`} className="font-semibold hover:underline">
            {person.name}
        </Link>
    ) : (
        <span className="font-semibold">{person.name}</span>
    );
}

/** One person's box; managers get a toggle with the size of their team. */
function PersonBox({ node, view }: { node: Node; view: ViewProps }) {
    const isCollapsed = view.collapsed.has(node.id);

    return (
        <div
            ref={(element) => view.register(node.id, element)}
            className={cn(
                'bg-card w-48 rounded-lg border px-3 py-2 text-center text-sm shadow-xs',
                view.matches.has(node.id) && 'ring-primary ring-2',
                node.id === view.selfId && 'border-primary',
            )}
        >
            <PersonName person={node} canOpenFiles={view.canOpenFiles} />
            {node.id === view.selfId && (
                <Badge variant="secondary" className="ml-1 align-middle text-[10px]">
                    Vous
                </Badge>
            )}
            {node.job_title && <p className="text-muted-foreground truncate text-xs">{node.job_title}</p>}
            {node.department && <p className="text-muted-foreground truncate text-[11px] uppercase">{node.department}</p>}
            {node.reports.length > 0 && (
                <button
                    type="button"
                    onClick={() => view.toggle(node.id)}
                    aria-expanded={!isCollapsed}
                    className="text-muted-foreground hover:text-foreground mt-1 inline-flex items-center gap-1 text-xs"
                >
                    {isCollapsed ? <ChevronRight className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                    {node.size} personne{node.size > 1 ? 's' : ''}
                </button>
            )}
        </div>
    );
}

/** People without a team under a manager, stacked in one card to keep the chart narrow. */
function TeamCard({ members, view }: { members: Node[]; view: ViewProps }) {
    return (
        // Divs, not a list: list items inside the chart would get its connector lines.
        <div role="list" className="bg-muted/40 w-52 divide-y rounded-lg border text-left text-sm">
            {members.map((member) => (
                <div
                    role="listitem"
                    key={member.id}
                    ref={(element) => view.register(member.id, element)}
                    className={cn('px-3 py-1.5', view.matches.has(member.id) && 'bg-primary/10 ring-primary ring-2 ring-inset')}
                >
                    <PersonName person={member} canOpenFiles={view.canOpenFiles} />
                    {member.id === view.selfId && (
                        <Badge variant="secondary" className="ml-1 align-middle text-[10px]">
                            Vous
                        </Badge>
                    )}
                    {member.job_title && <p className="text-muted-foreground truncate text-xs">{member.job_title}</p>}
                </div>
            ))}
        </div>
    );
}

/** A manager and, below, their managers (branching) and the rest of their team (one card). */
function Branch({ node, view }: { node: Node; view: ViewProps }) {
    const managers = node.reports.filter((report) => report.reports.length > 0);
    const team = node.reports.filter((report) => report.reports.length === 0);
    const open = node.reports.length > 0 && !view.collapsed.has(node.id);

    return (
        <li>
            <PersonBox node={node} view={view} />
            {open && (
                <ul>
                    {team.length > 0 && (
                        <li>
                            <TeamCard members={team} view={view} />
                        </li>
                    )}
                    {managers.map((manager) => (
                        <Branch key={manager.id} node={manager} view={view} />
                    ))}
                </ul>
            )}
        </li>
    );
}

/** Phone layout: the same tree as an indented outline. */
function Outline({ nodes, view }: { nodes: Node[]; view: ViewProps }) {
    return (
        <ul className="space-y-1">
            {nodes.map((node) => (
                <li key={node.id}>
                    <div className={cn('flex items-start gap-1 rounded px-1 py-1 text-sm', view.matches.has(node.id) && 'bg-primary/10')}>
                        {node.reports.length > 0 ? (
                            <button
                                type="button"
                                onClick={() => view.toggle(node.id)}
                                aria-expanded={!view.collapsed.has(node.id)}
                                className="mt-0.5"
                            >
                                {view.collapsed.has(node.id) ? <ChevronRight className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                                <span className="sr-only">Afficher ou masquer l'équipe</span>
                            </button>
                        ) : (
                            <span className="w-4 shrink-0" />
                        )}
                        <div>
                            <PersonName person={node} canOpenFiles={view.canOpenFiles} />
                            {node.id === view.selfId && <span className="text-muted-foreground"> (vous)</span>}
                            <p className="text-muted-foreground text-xs">{[node.job_title, node.department].filter(Boolean).join(' · ')}</p>
                        </div>
                    </div>
                    {node.reports.length > 0 && !view.collapsed.has(node.id) && (
                        <div className="ml-3 border-l pl-2">
                            <Outline nodes={node.reports} view={view} />
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}

/** Who reports to whom, from the "Responsable" links. Everyone sees it; HR can open a file. */
export default function OrgChart({ people }: { people: Person[] }) {
    const { auth } = usePage<SharedData>().props;
    const canOpenFiles = usePermissions().view_hr;
    const roots = useMemo(() => buildTree(people), [people]);
    const trees = roots.filter((root) => root.reports.length > 0);
    const alone = roots.filter((root) => root.reports.length === 0);

    const [collapsed, setCollapsed] = useState<Set<number>>(new Set());
    const [query, setQuery] = useState('');
    const elements = useRef(new Map<number, HTMLElement>());
    const scrollPending = useRef(false);

    const matches = useMemo(() => {
        const q = normalize(query.trim());
        if (q.length < 2) return new Set<number>();
        return new Set(people.filter((p) => normalize([p.name, p.job_title, p.department].filter(Boolean).join(' ')).includes(q)).map((p) => p.id));
    }, [people, query]);

    // Open every branch that hides a match, then bring the first one into view.
    useEffect(() => {
        if (matches.size === 0) return;
        const byId = new Map(people.map((p) => [p.id, p]));
        const ancestors = new Set<number>();
        for (const id of matches) {
            for (let manager = byId.get(id)?.manager_id; manager; manager = byId.get(manager)?.manager_id ?? null) {
                ancestors.add(manager);
            }
        }
        scrollPending.current = true;
        setCollapsed((current) => new Set([...current].filter((id) => !ancestors.has(id))));
    }, [matches, people]);

    // Once the opened branches are rendered, scroll to the first match.
    useEffect(() => {
        if (!scrollPending.current) return;
        scrollPending.current = false;
        const first = people.find((p) => matches.has(p.id));
        const element = first ? elements.current.get(first.id) : undefined;
        element?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
    }, [collapsed, matches, people]);

    const view: ViewProps = {
        collapsed,
        matches,
        selfId: auth.user.id,
        canOpenFiles,
        toggle: (id) =>
            setCollapsed((current) => {
                const next = new Set(current);
                if (next.has(id)) next.delete(id);
                else next.add(id);
                return next;
            }),
        register: (id, element) => {
            // The desktop chart and the phone outline both render; keep the visible one.
            if (element && element.offsetParent !== null) elements.current.set(id, element);
        },
    };

    const managerIds = new Set(people.map((p) => p.manager_id).filter(Boolean));
    const allManagers = people.filter((p) => managerIds.has(p.id)).map((p) => p.id);
    const managers = allManagers.length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organigramme" />
            <div className="space-y-6 px-4 py-10">
                <div className="mx-auto flex max-w-6xl flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Organigramme</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {people.length} personnes, {managers} responsable{managers > 1 ? 's' : ''} d'équipe.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2" />
                            <Input
                                type="search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Nom, poste, département…"
                                aria-label="Rechercher une personne"
                                className="w-64 pl-8"
                            />
                        </div>
                        {query.trim().length >= 2 && (
                            <span className="text-muted-foreground text-sm" role="status">
                                {matches.size} résultat{matches.size > 1 ? 's' : ''}
                            </span>
                        )}
                        <Button variant="outline" size="sm" onClick={() => setCollapsed(new Set())}>
                            Tout déplier
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setCollapsed(new Set(allManagers.filter((id) => !trees.some((t) => t.id === id))))}
                        >
                            Replier les équipes
                        </Button>
                    </div>
                </div>

                {trees.length === 0 ? (
                    <p className="text-muted-foreground mx-auto max-w-6xl text-sm">
                        Aucun lien responsable/équipe pour l'instant : le service RH les renseigne dans le dossier de chaque employé.
                    </p>
                ) : (
                    <>
                        <div className="org-tree hidden overflow-x-auto pb-4 md:block">
                            <ul className="mx-auto w-max min-w-full">
                                {trees.map((tree) => (
                                    <Branch key={tree.id} node={tree} view={view} />
                                ))}
                            </ul>
                        </div>
                        <div className="md:hidden">
                            <Outline nodes={trees} view={view} />
                        </div>
                    </>
                )}

                {alone.length > 0 && (
                    <section className="mx-auto max-w-6xl space-y-2">
                        <h2 className="text-sm font-semibold">Sans responsable ni équipe</h2>
                        <p className="text-muted-foreground text-xs">
                            {canOpenFiles
                                ? 'Indiquez leur responsable dans leur dossier pour les placer dans l’organigramme.'
                                : 'Rattachement en cours.'}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {alone.map((person) => (
                                <div
                                    key={person.id}
                                    ref={(element) => view.register(person.id, element)}
                                    className={cn('bg-card rounded-lg border px-3 py-1.5 text-sm', matches.has(person.id) && 'ring-primary ring-2')}
                                >
                                    <PersonName person={person} canOpenFiles={canOpenFiles} />
                                    {person.job_title && <span className="text-muted-foreground"> · {person.job_title}</span>}
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
