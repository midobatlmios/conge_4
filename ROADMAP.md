# Roadmap — taking Gestion des Congés to the next level

Ordered by impact. Each item says **why it matters** and roughly **how big** it is
(S = a few hours, M = 1–2 days, L = several days).

Already done: leave rules (working days, holidays, annual allowance, overlaps),
French UI, closed registration, admin safeguards, code structure (enums, service,
policy, form requests), check-only CI.

---

## 1. Must-have before real use

| # | Item | Why | Size |
|---|------|-----|------|
| 1.1 | **Real email sending** (SMTP / Mailgun / Brevo) | "Mot de passe oublié" can't send its link today; employees should also get an email when a request is accepted/refused | S |
| 1.2 | **Deactivate employees** (instead of deleting) | People leave; their leave history must stay for payroll and audits | S |
| 1.3 | **Holidays managed in the admin UI** (DB table instead of `config/conge.php`) | Eid / Mawlid dates change every year; HR must add them without a developer | M |
| 1.4 | **Audit history** on each request (who created, edited, accepted/refused, when) | Disputes and labour inspections need a trace | M |
| 1.5 | **Production hosting**: PHP 8.3/8.4, HTTPS, daily DB backups, error monitoring (Sentry) | This machine runs PHP 8.5, which the dependencies don't support; no backups = data loss risk | M |

## 2. HR features that make it a real product

| # | Item | Why | Size |
|---|------|-----|------|
| 2.1 | **Employee records**: matricule, department, job title, hire date, manager | Everything below depends on it | M |
| 2.2 | **Monthly accrual**: 1.5 days per month worked (Moroccan labour code) + seniority bonus (+1.5 days per 5 years) | A flat 18 days is wrong for new hires and long-serving staff | M |
| 2.3 | **Carry-over** of unused days to next year (with a cap / expiry date) | Common HR rule; avoids losing days on 1 January | S |
| 2.4 | **Two-step approval**: manager first, then HR | One admin approving everything doesn't scale past a small team | L |
| 2.5 | **Team calendar** (month view: who is off when) | Managers avoid half the team leaving at once; most-requested feature in leave apps | M |
| 2.6 | **Cancel an accepted leave** (cancellation request → admin approves → days returned) | Plans change; today an accepted request is stuck | S |
| 2.7 | **Half days** (morning / afternoon) | Very common need | M |
| 2.8 | **Supporting documents** (marriage / death / birth certificate upload) | Required for exceptional leave | S |
| 2.9 | **Payroll export** (Excel / PDF per month: unpaid days per employee) | HR uses it every month for salaries | S |
| 2.10 | **Admin dashboard**: who's off today, pending count, days used per department | Gives HR an overview at a glance | M |

## 3. Code quality & reliability

| # | Item | Why | Size |
|---|------|-----|------|
| 3.1 | **Frontend forms → Inertia `useForm`** (replace axios + manual state) | Less code, consistent errors and loading states everywhere | M |
| 3.2 | **End-to-end tests** (Playwright or Laravel Dusk): login → submit → approve → notified | Catches bugs like the broken password reset automatically | M |
| 3.3 | **Static analysis** (Larastan level 5+) in CI | Finds type bugs before they reach users | S |
| 3.4 | **Frontend unit tests** (Vitest) for `resources/js/lib/leave.ts` | Keeps the browser day-count in sync with the server | S |
| 3.5 | **Single source for leave rules**: frontend reads everything from the server (no duplicated logic) | Avoids the two copies drifting apart | S |
| 3.6 | **Git workflow**: branches + pull requests + meaningful commit messages + CHANGELOG | Reviewable history; looks professional to employers | S |

## 4. Polish & experience

| # | Item | Why | Size |
|---|------|-----|------|
| 4.1 | ✅ **Toast notifications** for success/error instead of inline banners + page reloads (done: server messages shown as toasts on every page, errors of actions too) | Feels like a modern app | S |
| 4.2 | ✅ **Confirm dialogs** using the app's own modal instead of `window.confirm` (done: every confirm and prompt, incl. refusal reasons and the CNSS date) | Consistent look, works in French | S |
| 4.3 | ✅ **Loading skeletons** and empty-state illustrations (done: empty states with a next step on the main lists; placeholders while notifications and history load) | Feels faster and friendlier | S |
| 4.4 | ✅ **Accessibility pass** (keyboard navigation, labels, contrast) (done: WCAG 2.1 AA checked with axe-core in the E2E tests, light and dark mode; skip link, current page in the menu, focus rings) | Required in many companies; better for everyone | S |
| 4.5 | **Progressive Web App** (installable on phone) | Employees check balance from their phone | M |
| 4.6 | **Arabic / English languages** (i18n) | Useful in Morocco for multilingual teams | M |

## 5. Portfolio / presentation

| # | Item | Why | Size |
|---|------|-----|------|
| 5.1 | **Screenshots + demo GIF in README** | First thing a recruiter sees | S |
| 5.2 | **Public demo** (seeded with fake data, reset nightly) | Lets people try it without installing | M |
| 5.3 | **Architecture section** in README (diagram of request → policy → form request → service) | Shows you understand the design | S |
| 5.4 | **HR user guide** (short PDF or page in French) | Real users need it; shows product thinking | S |

## 6. From leave manager to HR application (SIRH)

What turns a leave module into a tool HR runs the company with: the whole
employee lifecycle, documents, every kind of absence, reporting and compliance.

### Everyday HR work

| # | Item | Why | Size |
|---|------|-----|------|
| 6.1 | ✅ **Sick leave (congé maladie)** (done: declared within 48 h, no manager step, certificate required, CNSS dossier tracking, sick days in payroll): own type and counter, medical certificate required, CNSS declaration reminder | The most frequent absence; no HR tool is credible without it | M |
| 6.2 | ✅ **Administrative documents on request** (done: employee asks, RH issues a numbered PDF; company page "Entreprise"; salary on contracts): "Attestation de travail" / "Attestation de salaire" generated as PDF with the company letterhead and the employee record | One of HR's most frequent requests in Moroccan companies; instant visible value | M |
| 6.3 | ✅ **Contracts** (done: history per employee, legal trial period suggested from the category, alerts on the employee page and Vue d'ensemble): type (CDI, CDD, stage, ANAPEC), start/end dates, trial period, alerts ("CDD de X se termine dans 30 jours", "fin de période d'essai") | HR tracks contracts before anything else | M |

### Complete employee record

| # | Item | Why | Size |
|---|------|-----|------|
| 6.4 | ✅ **Personal & administrative data**: CIN, CNSS number, birth date, address, RIB, emergency contact (HR-only access) — done: employee page `/admin/users/{id}`, encrypted fields, RIB key check, employee reads own data in Paramètres | Needed for payroll, declarations and attestations | M |
| 6.5 | ✅ **Employee document folder**: contract, CIN copy, diplomas (private storage, as for leave certificates) — done: categories, optional expiry date with 30-day alert (employee page + Vue d'ensemble), employee reads own in Paramètres → Mes documents | Replaces the paper file in the HR cabinet | S |
| 6.6 | ✅ **Org chart** built from the manager links (done: "Organigramme" page for everyone, search, collapsible teams, outline on phones) | Shows the structure at a glance; already have the data | S |
| 6.7 | ✅ **Import employees from Excel** (with a downloadable template and error report) — done: check before import (all or nothing), updates existing employees, invitation e-mails | No company re-types 50 people by hand | M |

### Steering and day-to-day running

| # | Item | Why | Size |
|---|------|-----|------|
| 6.8 | ✅ **HR indicators** (done: "Indicateurs RH" page per year and department, year-end report in Excel): absence rate, headcount over time, turnover, days expiring soon, year-end report | What HR presents to management | M |
| 6.9 | ✅ **Reminders** (done: managers about requests waiting 3 working days, HR daily summary, data-protection notice; `reminders:send` scheduled weekdays 08:00): requests waiting > 3 days nudge the manager/HR; carried-over days expiring on 31 March trigger an alert in February | Nothing gets stuck or lost | S |
| 6.10 | ✅ **Delegation**: a manager on leave hands their approvals to someone else (done: set by the manager on « Mon équipe » or by HR, chosen dates, both can decide, notifications and reminders go to the delegate) | Approvals don't stop during holidays | S |
| 6.11 | ✅ **Calendar export** (iCal feed of approved leave for Outlook / Google Calendar) — done: « Mes congés » and « Absences de mon équipe » (names only) via private links in Paramètres → Calendrier | Leave shows up where people already plan | S |

### Trust and compliance

| # | Item | Why | Size |
|---|------|-----|------|
| 6.12 | ✅ **Personal data protection (loi 09-08 / CNDP)** (done: notice accepted at first login, access log for the Administrateur, data export, retention periods, register page with the CNDP checklist): consent, retention periods, export of an employee's data on request, access limited by role | A buyer checks this first; personal data is now stored | M |
| 6.13 | ✅ **Finer roles**: HR, super-admin, read-only (payroll / accountant) instead of one admin role — done: roles admin / rh / paie / user | Matches how companies split responsibilities | M |
| 6.14 | **Company settings** (started: name, address, ICE/RC/IF/CNSS, signatory and logo on the "Entreprise" page; multi-site still to do): name, logo, address, ICE (used on attestations), several sites with their own holidays | One app per company, branded and multi-site | M |

### Presentation

| # | Item | Why | Size |
|---|------|-----|------|
| 6.15 | ✅ **Company branding** on every page and PDF (done: company name, logo or initials badge and accent color in the sidebar, sign-in pages and attestations) | Looks like the company's own tool | S |
| 6.16 | ✅ **Realistic demo data** (done: `php artisan demo:reset`, fictional company Atlas Distribution): ~30 employees, several departments, a year of history | An empty app looks like a prototype | S |

See also 4.5 (installable on phone) and 4.6 (Arabic / English).

---

### Suggested order

1. **1.1, 1.2, 3.1** — quick wins that remove real blockers.
2. **2.1 → 2.2 → 2.3** — employee records unlock accurate balances.
3. **2.5, 2.6, 2.9** — the features HR will notice most.
4. **1.3, 1.4, 3.2** — robustness.
5. **1.5** when it's time to deploy; **section 5** before showing it to anyone.
6. **6.1 → 6.2 → 6.3 → 6.16 + 6.15** — sick leave, attestations, contracts, then demo data and branding: the shortest path to being seen as an HR application.
