<?php

/*
|--------------------------------------------------------------------------
| Leave (congé) rules
|--------------------------------------------------------------------------
|
| Business rules for leave requests. Change them here rather than in code.
|
*/

return [

    // Days of annual leave earned over a full calendar year (Code du travail art. 231):
    // earned month by month, i.e. 18 / 12 = 1.5 days at the end of each month worked.
    'max_days_per_year' => 18,

    // Seniority bonus (art. 232): extra days per year for every full block of
    // service years, counted on 1 January, with a cap on the yearly total.
    'seniority_bonus_days' => 1.5,
    'seniority_years' => 5,
    'max_days_with_seniority' => 30,

    // Unused days of the previous year carry over to the new year (all of them,
    // never older ones) and must be used by this day (MM-DD), then they are lost.
    'carry_over_expiry' => '03-31',

    // Annual leave is only due after this many months of service (art. 231).
    // Employees may still submit; the admin cannot accept before that date.
    'eligibility_months' => 6,

    // Leave types whose every working day uses up the yearly allowance above.
    'counted_types' => ['annuel'],

    // Leave types that take a fixed number of days from the allowance, whatever
    // their length (the rest is paid). Deducted in the year the leave starts.
    // Types in neither list (naissance, sans_solde, récupération) are not deducted.
    'fixed_deductions' => [
        'mariage' => 2,
        'deces' => 2,
    ],

    // Leave types that can start in the afternoon and/or end at noon (half days).
    // Exceptional leave (mariage, naissance, décès) is always taken in whole days.
    'half_day_types' => ['annuel', 'sans_solde', 'recuperation'],

    // Leave types that need a supporting document (certificate) before HR can
    // accept them. The employee may submit first and upload it later.
    'document_types' => ['mariage', 'naissance', 'deces', 'maladie'],

    // Sick leave (congé maladie): the employee must tell the employer within 48 hours
    // (art. 271), so it can be declared up to this many days after it started; HR can
    // enter older ones. From cnss_min_days calendar days, the CNSS pays daily
    // allowances and the dossier must be filed within cnss_filing_days.
    'sick_leave_backdate_days' => 2,
    'cnss_min_days' => 4,
    'cnss_filing_days' => 30,

    // Supporting documents: allowed file types, maximum size (kilobytes) and count per request.
    'document_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    'document_max_kb' => 5120,
    'documents_per_request' => 5,

    // Employee document folder (contract, CIN copy, diplomas…): same file types,
    // larger scans allowed. Documents expiring within this many days are flagged.
    'employee_document_max_kb' => 10240,
    'document_expiry_alert_days' => 30,

    // Reminders, sent each working morning by `php artisan reminders:send` (scheduled
    // in routes/console.php; the server needs the Laravel scheduler in its cron).
    // - a manager is reminded of requests waiting for them this many working days,
    //   then again every `manager_repeat_days` working days while they still wait;
    // - HR gets a daily summary of what to do (requests waiting this long, CNSS,
    //   contracts, documents, attestations…), only on days with something to do;
    // - an employee who has not accepted the data protection notice this many days
    //   after their account was created is reminded, at most once a week.
    'reminders' => [
        'request_waiting_days' => 3,
        'manager_repeat_days' => 3,
        'notice_days' => 7,
        'send_at' => '08:00',
        // Local time of the company (the app itself stores dates in UTC).
        'timezone' => 'Africa/Casablanca',
    ],

    // Personal data retention (loi 09-08: kept no longer than needed). Applied by the
    // Administrateur from "Protection des données" (or `php artisan privacy:retention`).
    // - medical certificates of sick leave: deleted this many years after the leave;
    // - personal data (CIN, RIB, address…), document folder and attestations of a
    //   former employee: deleted this many years after departure;
    // - the former employee is then anonymized (name, e-mail, comments) after this
    //   many years; leave and payroll history is kept, without identity.
    'retention' => [
        'medical_certificates_years' => 2,
        'personal_data_years' => 5,
        'anonymize_after_years' => 10,
    ],

    // Contracts: a CDD (ANAPEC, stage) or a trial period ending within this many
    // days is flagged on the overview and the employee page.
    'contract_alert_days' => 30,

    // Maximum number of working days for fixed-length leave types.
    'type_limits' => [
        'mariage' => 4,
        'naissance' => 3,
        'deces' => 3,
    ],

    // ISO weekday numbers that count as working days (1 = Monday … 7 = Sunday).
    'working_days' => [1, 2, 3, 4, 5],

    // Public holidays are not here: HR manages them in the app
    // (Administration → Jours fériés), stored in the holidays table.

];
