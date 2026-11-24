# conge_4

Une plateforme de gestion des congés (leave management) built with **Laravel 12**, **React + TypeScript**, and **Inertia.js**.

## Features

- Employees submit leave requests (`demandes`) and can edit or delete them while they are still pending.
- Admins see every request, accept or refuse them, and manage user accounts.
- Employees get an in-app notification when a request is accepted or refused.
- Business rules, enforced on the server (configurable in `config/conge.php`):
  - Days are counted in **working days**: Monday to Friday, excluding public holidays. Fixed-date Moroccan holidays are listed in the config. Religious holidays move every year, so add them as `YYYY-MM-DD` entries.
  - Each employee has **18 days of annual leave (`annuel`) per calendar year**, checked when an admin accepts a request. A request that spans New Year counts toward each year separately.
  - `mariage` and `deces` each take **2 days** from the 18, whatever their length (the rest is paid). The deduction counts in the year the leave starts.
  - `naissance`, unpaid leave (`sans_solde`) and `recuperation` are tracked but don't use up the 18 days.
  - Per-type caps: `mariage` up to 4 working days, `naissance` and `deces` up to 3.
  - A request can't overlap another pending or accepted request from the same employee, and can't consist only of weekends or holidays.
  - Employees can't request leave starting in the past. Admins can, to record it after the fact.
  - The request date (`date_demande`) is set by the server when the request is submitted.
  - Admins can file a request on behalf of an employee.
  - A `recuperation` request requires a comment.
- Roles: `admin` and `user`. Admin pages live under `/admin`.
- There is no public sign-up: admins create accounts from **Gérer les employés**. An admin can't remove their own admin role, and the last admin can't delete their account.

## Requirements

- PHP 8.2+ and Composer
- Node.js 18+ and npm
- MySQL (for example via XAMPP) or SQLite

## Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Set the `DB_*` values in `.env` for your database, then:

```bash
php artisan migrate --seed
```

## Running locally

```bash
composer run dev     # Laravel server, queue worker and Vite together
```

Or run `php artisan serve` and `npm run dev` in two terminals, then open http://localhost:8000.

### Development accounts

Running `php artisan db:seed` creates these accounts if they don't already exist:

| Role  | Email               | Password   |
|-------|---------------------|------------|
| admin | admin@example.com   | `password` |
| user  | test@example.com    | `password` |

These are for local development only. Never seed them in production.

## Tests and checks

```bash
php artisan test           # Pest feature tests
vendor/bin/pint            # Format PHP (CI runs: vendor/bin/pint --test)
npm run format             # Format frontend (CI runs: npm run format:check)
npm run lint               # ESLint with auto-fix (CI runs: npm run lint:check)
npm run types              # TypeScript type check
```

The GitHub `linter` workflow only checks; it fails if any of these report a problem.

## Project layout

| Path | Contents |
|------|----------|
| `config/conge.php` | Leave rules: yearly allowance, deductions, type limits, working days, holidays |
| `app/Enums/` | `LeaveType`, `LeaveStatus`, `Role`: the allowed values, with French labels |
| `app/Services/LeaveCalculator.php` | All day arithmetic: working days, allowance used and remaining |
| `app/Policies/DemandePolicy.php` | Who may view, create for others, edit, delete or accept/refuse requests |
| `app/Http/Requests/` | Validation: leave requests (dates, limits, overlaps) and admin user forms |
| `app/Http/Controllers/DemandesController.php` | Leave requests: listing, creation, approval |
| `app/Http/Controllers/AdminController.php` | Employee accounts (admin only) |
| `app/Http/Middleware/RoleMiddleware.php` | `role:admin` route guard |
| `resources/js/lib/leave.ts` | Frontend copy of the day-counting rules, for live feedback in forms |
| `resources/js/pages/` | Inertia pages (Dashboard, Demandes, Admin/Users, auth, settings) |
| `tests/Feature/` | Tests for permissions, leave rules and admin safeguards |
