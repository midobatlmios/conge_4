import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Account roles (mirrors App\Enums\Role). Managers are not a role: anyone with
 * employees reporting to them approves their requests first.
 */
export type Role = 'admin' | 'rh' | 'paie' | 'user';

export const ROLE_LABELS: Record<Role, string> = {
    admin: 'Administrateur',
    rh: 'RH',
    paie: 'Paie (lecture seule)',
    user: 'Employé',
};

/** A role the signed-in user may give (sent by the server). */
export type RoleOption = { value: Role; label: string };

/** What the signed-in user may do (User::permissions() on the server). */
export type Permissions = {
    /** Admin, RH, Paie: every request, employee files, payroll export, overview. */
    view_hr: boolean;
    /** Admin, RH: changes HR data and decides on leave. */
    manage_hr: boolean;
    /** Admin: gives and removes the Admin, RH and Paie roles. */
    manage_roles: boolean;
};

const NONE: Permissions = { view_hr: false, manage_hr: false, manage_roles: false };

export function usePermissions(): Permissions {
    return usePage<SharedData>().props.auth?.user?.can ?? NONE;
}

/** Edit, reset the password of, or deactivate an account (User::canManageAccount). */
export function canManageAccount(can: Permissions, targetRole: Role): boolean {
    return can.manage_roles || (can.manage_hr && targetRole === 'user');
}
