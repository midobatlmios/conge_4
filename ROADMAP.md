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
| 4.1 | **Toast notifications** for success/error instead of inline banners + page reloads | Feels like a modern app | S |
| 4.2 | **Confirm dialogs** using the app's own modal instead of `window.confirm` | Consistent look, works in French | S |
| 4.3 | **Loading skeletons** and empty-state illustrations | Feels faster and friendlier | S |
| 4.4 | **Accessibility pass** (keyboard navigation, labels, contrast) | Required in many companies; better for everyone | S |
| 4.5 | **Progressive Web App** (installable on phone) | Employees check balance from their phone | M |
| 4.6 | **Arabic / English languages** (i18n) | Useful in Morocco for multilingual teams | M |

## 5. Portfolio / presentation

| # | Item | Why | Size |
|---|------|-----|------|
| 5.1 | **Screenshots + demo GIF in README** | First thing a recruiter sees | S |
| 5.2 | **Public demo** (seeded with fake data, reset nightly) | Lets people try it without installing | M |
| 5.3 | **Architecture section** in README (diagram of request → policy → form request → service) | Shows you understand the design | S |
| 5.4 | **HR user guide** (short PDF or page in French) | Real users need it; shows product thinking | S |

---

### Suggested order

1. **1.1, 1.2, 3.1** — quick wins that remove real blockers.
2. **2.1 → 2.2 → 2.3** — employee records unlock accurate balances.
3. **2.5, 2.6, 2.9** — the features HR will notice most.
4. **1.3, 1.4, 3.2** — robustness.
5. **1.5** when it's time to deploy; **section 5** before showing it to anyone.
