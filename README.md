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
- **Sick leave** (`maladie`): declared, not approved by the manager. It goes straight to HR and the manager only gets an information notice (no medical detail). The employee can declare it up to 2 days after it started (art. 271, `sick_leave_backdate_days`); HR can enter older ones. HR accepts it once the medical certificate is attached. It never uses the annual allowance. Days off are counted in working days like other leave (payroll export column **Maladie**), and the calendar days the CNSS counts are shown too. From 4 calendar days (`cnss_min_days`) the CNSS dossier must be filed within 30 days (`cnss_filing_days`): **Vue d'ensemble** lists the ones not filed yet with their deadline, and HR records the filing date with **Dossier CNSS** on **Gérer les demandes**. The employee page shows the year's sick days.
- **Personal data** (CIN, CNSS number, birth date and place, family situation, address, personal contact, bank and RIB, emergency contact) lives on each employee's page (`/admin/users/{id}`), editable by admins only. The employee can read their own under **Paramètres → Données personnelles**; managers and colleagues never see it. CIN, CNSS, RIB, address and phone/e-mail are encrypted in the database with `APP_KEY`: **never change `APP_KEY` in production** without listing the old key in `APP_PREVIOUS_KEYS`, or that data becomes unreadable. RIBs are checked with their 2-digit key (mod 97).
- **Import from Excel** (**Gestion des employés → Importer depuis Excel**, Admin and RH): download the template (sheet *Employés*, plus an example and instructions), one row per employee: name, work e-mail and hire date are required; matricule, department (created if new), job title, manager (e-mail or matricule, in the app or in the same file), personal data (CIN, CNSS, RIB…) and the current contract (type, category, dates, gross salary) are optional. The file is checked row by row (same rules as the forms: RIB key, CIN unique, dates…) and **nothing is saved while any row has an error**; an error report can be downloaded. An employee already in the app (same e-mail or matricule) is updated, empty cells change nothing; RH cannot change Admin/RH/Paie accounts this way. New accounts have no password: **Envoyer les invitations** e-mails each one a link to choose it, valid 7 days (own token table, `invitations` password broker); a single invitation can also be sent from the employee's row menu.
- **Calendar subscriptions (iCal)**: in **Paramètres → Calendrier** each person creates private links that Outlook, Google Agenda or a phone subscribes to (they refresh on their own every few hours). *Mes congés* has their own leave with its type (waiting ones marked « en attente », tentative); managers and delegates also get *Absences de mon équipe*: accepted leave of their team, shown as « Nom – absent(e) » without the type, so nothing like sick leave reaches Google or Microsoft. The link contains a 48-character secret (`/calendrier/abonnement/{token}/me.ics`, rate-limited); « Créer de nouveaux liens » or « Désactiver » cuts the old ones off, and links of deactivated accounts stop working.
- **Delegation**: a manager hands the approval of their team's requests to a colleague for a period (« Mon équipe » → *Délégation pendant une absence*, dates pre-filled with their next leave); HR can set or end it from the manager's employee page. During the period the delegate is notified of new requests, gets the reminders, sees the team's requests on « Mon équipe » (marked *Par délégation de …*) and can approve or refuse them; the manager can still decide too, and each decision records who took it. A delegate never decides on their own request. Ending a delegation early stops it from today.
- **Reminders** (`php artisan reminders:send`, scheduled every weekday at 08:00 Morocco time; public holidays are skipped). A manager is reminded, in the app and by e-mail, of team requests waiting for them for 3 working days, then every 3 working days while they still wait. Admin and RH get **one morning summary** on days with something to do: requests waiting for HR (validated by the manager, without a manager, or sick leave), cancellation and attestation requests, CNSS dossiers, contracts and trial periods ending, documents expiring (and, for the Administrateur, data due for deletion). Employees who have not accepted the data-protection notice 7 days after their account was created get an e-mail, at most once a week. Settings in `config/conge.php` → `reminders`. `--dry-run` lists what would be sent.
  - **The scheduler must run on the server**: add `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1` to the crontab (on a development machine: `php artisan schedule:work`).
- **Personal data protection (loi 09-08)**:
  - **Notice**: every employee must read and accept the information notice (who processes their data, why, which data, who sees it, how long, their rights, the CNDP declaration number) at their next sign-in; the date is recorded. It stays available from the user menu (**Protection des données**).
  - **Access log**: each time someone views or changes another person's file, personal data, contract, folder document, leave certificate or attestation, or exports their data, it is logged (who, whose, what, when, IP; field names only, never values). Only the Administrateur can read it; nobody can edit it.
  - **Right of access**: the employee downloads a ZIP of everything held about them (readable summary, JSON, their documents, and who in HR consulted their data); HR can export it from the employee page.
  - **Retention** (`config/conge.php` → `retention`): medical certificates 2 years after the leave; personal data, folder and attestations 5 years after departure; anonymization 10 years after departure (leave and payroll history kept without identity). The Administrateur applies it from **Protection des données** (or `php artisan privacy:retention --apply`).
  - **Register page** (Administrateur): the processing register to print and the CNDP checklist (declaration, authorization for health data, transfers abroad if the host or e-mail provider is outside Morocco). The CNDP number and privacy contact are set on the **Entreprise** page.
- **Org chart** (**Organigramme**, every employee): who reports to whom, from the "Responsable" links between active employees. Managers branch out; the rest of each team is stacked in one card under its manager to keep the chart narrow. Teams can be collapsed, and the search (name, job title, department; accents ignored) opens the right branches and scrolls to the person. On a phone it becomes an indented list. Only names, job titles and departments are shown; Admin, RH and Paie can click a name to open the employee page. Someone whose manager left moves to the top; people with neither manager nor team are listed below the chart so HR can attach them.
- **HR indicators** (**Indicateurs RH**, Admin/RH/Paie), per year and optionally per department: headcount at each month end, hires, departures, turnover (departures ÷ average month-end headcount), average seniority, absenteeism (sick + unpaid days ÷ theoretical working days; annual leave, recovery and family events are reported apart as planned leave), sick days and stops, unused leave per employee and carried-over days about to expire. The current year stops today. **Bilan annuel (Excel)** downloads the same figures (summary, monthly, departments, every employee's unused days). Only booked leave counts; an employee is employed from the hire date until the account is deactivated.
- **Branding**: the company name, logo and accent color set on the **Entreprise** page appear in the sidebar header, on the sign-in and password pages and on attestations. Without a logo, a badge with the company's initials in the accent color is shown; without a company name, the app keeps "Gestion des congés". The logo is served publicly at `/brand/logo` (the sign-in page needs it). In dark mode the company name is shown in white for contrast.
- **Attestations** (de travail, de salaire) as PDF (barryvdh/laravel-dompdf). The employee asks under **Paramètres → Mes attestations** (optional purpose: visa, bank…); Admin and RH get an in-app + e-mail notification, then **Délivrer** or **Refuser** (with a reason) on the **Attestations** page, or issue one directly from the employee page. Each issued attestation gets a yearly number (`ATT-2026-0001`) and a frozen, encrypted copy of what it prints, so downloading it later gives the same document. It needs the company details (**Entreprise** page: name, address, city, signatory; ICE, RC, IF, CNSS and a PNG/JPG logo are optional), the employee's CIN, and for a salary attestation the gross monthly salary of the current contract (stored encrypted on the contract; amounts are also written in words). Nobody issues their own; Paie reads. The PDF uses the DejaVu Sans font bundled with dompdf (accents OK); the server needs the `mbstring` and `gd` PHP extensions.
- **Contracts** on each employee page, kept as a history (CDD → renewal → CDI): CDI, CDD, contrat ANAPEC, stage; staff category (cadre, employé, ouvrier); start/end dates (an end date is required except for a CDI); trial period end, which the form suggests from the Labour Code (CDI: cadre 3 months, employé 1.5 months, ouvrier 15 days; CDD: 1 day per week, max. 2 weeks up to 6 months, 1 month beyond) and can be marked renewed. An employee's contracts can't overlap. A CDD/ANAPEC/stage ending, or a trial period ending, within 30 days (`contract_alert_days`) is flagged on the employee page and on **Vue d'ensemble**, and so is a fixed-term contract that ended with nothing after it. The Users list shows the contract in force; the employee reads theirs under **Paramètres → Données personnelles**. Admin and RH edit (not their own), Paie reads.
- **Document folder** on each employee page: contract, CIN copy, residence card, diplomas, CV, RIB, medical aptitude… (PDF/JPG/PNG, 10 MB). Files are stored privately in `storage/app/private/employee-documents` under random names and only served by the app. Admin and RH add or remove them, Paie reads them, and the employee sees their own under **Paramètres → Mes documents**. An optional expiry date flags the document 30 days ahead (`config/conge.php` → `document_expiry_alert_days`) on the employee page and on **Vue d'ensemble**. Back up `storage/app/private` along with the database.
- Roles (`app/Enums/Role.php`):
  - `admin` (Administrateur): everything RH does, plus giving and removing the Admin, RH and Paie roles.
  - `rh` (RH): day-to-day HR: employees, leave decisions, personal data, holidays, departments, payroll export. RH creates and manages **plain employee accounts only**; it can't edit, reset or deactivate an Admin, RH or Paie account.
  - `paie` (Paie, read-only): payroll export, overview, every leave request and the employee files (including RIB and CNSS), without changing anything.
  - `user` (Employé). Managers are not a role: anyone with employees reporting to them approves their requests first.
  - Nobody edits their own personal data; another RH/Admin does. HR pages live under `/admin`.
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
| rh    | rh@example.com      | `password` |
| paie  | paie@example.com    | `password` |
| user  | test@example.com    | `password` |

These are for local development only. Never seed them in production.

### Demo data

`php artisan demo:reset` **erases the database** (and uploaded files) and loads a fictional company, *Atlas Distribution*: 27 people in 6 departments with managers, personal data, contracts (CDI, CDD ending soon, ANAPEC, stage, trial periods), document folders (some expiring), about a year of leave history (annual, recovery, sick leave with CNSS follow-up, unpaid, exceptional), attestations and a few cases waiting for HR. Dates are relative to the day you run it. It refuses to run when `APP_ENV=production`. Every account uses the password `password`:

| Role | Email |
|------|-------|
| Administrateur | admin@atlas-demo.test |
| RH | rh@atlas-demo.test |
| Paie (read-only) | paie@atlas-demo.test |
| Manager | manager@atlas-demo.test |
| Employee | employe@atlas-demo.test |

The religious holidays it adds for the current year are approximate. The `.test` addresses can't receive mail: actions that notify someone still work, the e-mail just fails quietly (the in-app notification is kept).

## Tests and checks

```bash
php artisan test           # Pest feature tests
vendor/bin/pint            # Format PHP (CI runs: vendor/bin/pint --test)
npm run format             # Format frontend (CI runs: npm run format:check)
npm run lint               # ESLint with auto-fix (CI runs: npm run lint:check)
npm run types              # TypeScript type check
```

The GitHub `linter` workflow only checks; it fails if any of these report a problem.

### End-to-end tests (Playwright)

`tests/e2e/accessibility.spec.ts` checks every page against WCAG 2.1 A/AA with axe-core (`@axe-core/playwright`), as an employee, a manager, RH and the Administrateur, in light and dark mode; any violation fails the run with the element to fix. Keep it green when adding pages: add the new URL to the list in that file.


A real browser follows a leave request through the app: the employee asks, their
manager validates, HR accepts, and the employee is notified (`tests/e2e`).

```bash
npx playwright install chromium   # once: downloads the browser
npm run build                     # the tests use the built frontend
npm run test:e2e
```

The tests start their own server on port 8001 with `.env.e2e`: a throwaway SQLite
database (`database/e2e.sqlite`, rebuilt on every run from `E2eSeeder`) and emails
written to the log. Your development database is never touched. On failure,
screenshots and a trace are saved in `test-results/` (`npx playwright show-trace …`).
The GitHub `tests` workflow runs them in a separate `e2e` job.

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
