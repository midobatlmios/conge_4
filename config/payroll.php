<?php

/*
| Moroccan payroll rates used to compute payslips (PayslipCalculator).
| They change with the finance law (loi de finances) and CNSS decisions:
| have them checked by the company's accountant every January. Amounts in MAD.
*/

return [
    // Legal monthly hours (44 h × 52 weeks ÷ 12): hourly rate = monthly salary ÷ this.
    'monthly_hours' => 191,

    // Value of one day of unpaid leave or unjustified absence: monthly salary ÷ this
    // (usual practice: 26 working days, 6-day basis); also the CNSS days of a full month.
    'days_per_month' => 26,

    // Lines Paie can add to a payslip; "taxable" lines enter the gross (CNSS, AMO, IR).
    // Transport and panier allowances are exempt only within legal caps: Paie enters
    // any part above the cap as a taxable line.
    'bonus_presets' => [
        ['label' => 'Prime de rendement', 'taxable' => true],
        ['label' => 'Prime exceptionnelle', 'taxable' => true],
        ['label' => 'Indemnité de transport', 'taxable' => false],
        ['label' => 'Prime de panier', 'taxable' => false],
    ],

    // Overtime premiums (Code du travail, art. 201): +25 % day, +50 % night or rest day, +100 % rest-day night.
    'overtime_rates' => ['25' => 1.25, '50' => 1.50, '100' => 2.00],

    // Prime d'ancienneté (art. 350): rate from completed years of service.
    'seniority' => [25 => 0.25, 20 => 0.20, 12 => 0.15, 5 => 0.10, 2 => 0.05],

    // Employee contributions.
    'cnss_employee_rate' => 0.0448,   // prestations sociales, on gross capped at the ceiling
    'cnss_ceiling' => 6000,
    'amo_employee_rate' => 0.0226,    // assurance maladie obligatoire, uncapped

    // Employer contributions (shown on the payslip and in the payroll cost).
    'employer' => [
        'cnss' => ['label' => 'CNSS prestations sociales', 'rate' => 0.0898, 'capped' => true],
        'family' => ['label' => 'Allocations familiales', 'rate' => 0.0640, 'capped' => false],
        'training' => ['label' => 'Taxe de formation professionnelle', 'rate' => 0.0160, 'capped' => false],
        'amo' => ['label' => 'AMO (dont participation)', 'rate' => 0.0411, 'capped' => false],
    ],

    // Frais professionnels: 35 % while the annual gross taxable ≤ 78 000, 25 % above; capped at 35 000 a year.
    'professional_expenses' => ['threshold_monthly' => 6500, 'low_rate' => 0.35, 'high_rate' => 0.25, 'cap_monthly' => 35000 / 12],

    // Monthly IR barème (loi de finances 2025): [up to, rate, amount to deduct].
    'ir_brackets' => [
        [40000 / 12, 0.00, 0],
        [60000 / 12, 0.10, 4000 / 12],
        [80000 / 12, 0.20, 10000 / 12],
        [100000 / 12, 0.30, 18000 / 12],
        [180000 / 12, 0.34, 22000 / 12],
        [PHP_FLOAT_MAX, 0.37, 27400 / 12],
    ],

    // Family deduction from the IR: per dependent (spouse if married, children), capped.
    'family_deduction_per_person' => 500 / 12,
    'family_deduction_max_persons' => 6,
];
