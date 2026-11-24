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

    // Public holidays are not here: HR manages them in the app
    // (Administration → Jours fériés), stored in the holidays table.

];
