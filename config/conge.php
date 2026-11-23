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

    // Days of annual leave each employee gets per calendar year.
    'max_days_per_year' => 18,

    // Leave types whose every working day uses up the yearly allowance above.
    'counted_types' => ['annuel'],

    // Leave types that take a fixed number of days from the allowance, whatever
    // their length (the rest is paid). Deducted in the year the leave starts.
    // Types in neither list (naissance, sans_solde, récupération) are not deducted.
    'fixed_deductions' => [
        'mariage' => 2,
        'deces' => 2,
    ],

    // Maximum number of working days for fixed-length leave types.
    'type_limits' => [
        'mariage' => 4,
        'naissance' => 3,
        'deces' => 3,
    ],

    // ISO weekday numbers that count as working days (1 = Monday … 7 = Sunday).
    'working_days' => [1, 2, 3, 4, 5],

    // Public holidays that are not counted as leave days.
    // "MM-DD" repeats every year; "YYYY-MM-DD" applies to that date only
    // (use it for religious holidays, which move every year).
    'holidays' => [
        '01-01', // Nouvel An
        '01-11', // Manifeste de l'Indépendance
        '01-14', // Nouvel An amazigh
        '05-01', // Fête du Travail
        '07-30', // Fête du Trône
        '08-14', // Allégeance Oued Eddahab
        '08-20', // Révolution du Roi et du Peuple
        '08-21', // Fête de la Jeunesse
        '11-06', // Marche Verte
        '11-18', // Fête de l'Indépendance
    ],

];
