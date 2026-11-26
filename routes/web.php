<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttestationController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelegationController;
use App\Http\Controllers\DemandeDocumentController;
use App\Http\Controllers\DemandesController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\EmploymentContractController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\IndicatorsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// The company logo, shown on the sign-in pages too (not sensitive).
Route::get('/brand/logo', [CompanyController::class, 'logo'])->name('brand.logo');

// Calendar subscriptions (iCal) read by Outlook / Google Calendar: the secret token is the access.
Route::get('/calendrier/abonnement/{token}/{feed}.ics', [CalendarFeedController::class, 'feed'])
    ->where(['token' => '[A-Za-z0-9]{48}', 'feed' => 'me|team'])
    ->middleware('throttle:60,1')
    ->name('calendar.feed');

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::redirect('/home', '/dashboard');

    // Leave requests. Who may edit or delete which request is decided by DemandePolicy.
    Route::get('/demandes', [DemandesController::class, 'index'])->name('demandes.index');
    Route::post('/demandes', [DemandesController::class, 'store'])->name('demandes.store');
    Route::put('/demandes/{demande}', [DemandesController::class, 'update'])->name('demandes.update');
    Route::delete('/demandes/{demande}', [DemandesController::class, 'destroy'])->name('demandes.destroy');
    Route::get('/demandes/{demande}/history', [DemandesController::class, 'history'])->name('demandes.history');

    // Supporting documents (certificates): access checked by DemandePolicy.
    Route::post('/demandes/{demande}/documents', [DemandeDocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DemandeDocumentController::class, 'show'])->name('documents.show');
    Route::delete('/documents/{document}', [DemandeDocumentController::class, 'destroy'])->name('documents.destroy');

    // Two-step approval: the employee's manager approves (then HR decides) or refuses.
    Route::get('/equipe', [TeamController::class, 'index'])->name('team');
    // A manager hands their approvals to someone for a period (HR can too, below).
    Route::post('/equipe/delegations', [DelegationController::class, 'store'])->name('delegations.store');
    Route::delete('/delegations/{delegation}', [DelegationController::class, 'destroy'])->name('delegations.destroy');
    Route::put('/demandes/{demande}/manager-approve', [DemandesController::class, 'managerApprove'])->name('demandes.manager-approve');
    Route::put('/demandes/{demande}/manager-refuse', [DemandesController::class, 'managerRefuse'])->name('demandes.manager-refuse');

    // Cancelling an accepted leave: the employee asks, an admin cancels or keeps it.
    Route::post('/demandes/{demande}/cancellation', [DemandesController::class, 'requestCancellation'])->name('demandes.request-cancellation');
    Route::put('/demandes/{demande}/cancel', [DemandesController::class, 'cancel'])->name('demandes.cancel');
    Route::put('/demandes/{demande}/keep', [DemandesController::class, 'keep'])->name('demandes.keep');

    // Sick leave: HR records when the CNSS dossier was filed.
    Route::put('/demandes/{demande}/cnss', [DemandesController::class, 'cnss'])->name('demandes.cnss');

    // Team calendar: who is off when (what each person sees is decided in the controller).
    Route::get('/calendrier', [CalendarController::class, 'index'])->name('calendar');

    // Data protection notice (accepted once), and the export of one's own data.
    Route::get('/protection-des-donnees', [PrivacyController::class, 'notice'])->name('privacy.notice');
    Route::post('/protection-des-donnees/accept', [PrivacyController::class, 'acknowledge'])->name('privacy.acknowledge');
    Route::get('/protection-des-donnees/export', [PrivacyController::class, 'exportOwn'])->name('privacy.export');

    // Org chart from the manager links (everyone; no personal data).
    Route::get('/organigramme', [OrgChartController::class, 'index'])->name('org-chart');

    // A document of an employee's folder: HR (incl. Paie) and the employee themself.
    Route::get('/employee-documents/{document}', [EmployeeDocumentController::class, 'show'])->name('employee-documents.show');

    // Attestations: the employee asks (Paramètres → Mes attestations), HR issues the PDF.
    Route::post('/attestations', [AttestationController::class, 'request'])->name('attestations.request');
    Route::get('/attestations/{attestation}/pdf', [AttestationController::class, 'pdf'])->name('attestations.pdf');
});

// HR pages. Admin, RH and Paie read them (Paie is read-only, for payroll).
Route::middleware(['auth', 'verified', 'role:admin,rh,paie'])->prefix('admin')->name('admin.')->group(function () {
    // HR overview: who is off, what waits for a decision, leave per department.
    Route::get('/overview', [OverviewController::class, 'index'])->name('overview');

    // Employee list and file: record + personal data (CIN, CNSS, RIB…).
    Route::get('/users', [AdminController::class, 'users'])->name('users.index');
    Route::get('/users/{user}', [EmployeeController::class, 'show'])->name('users.show');

    // Monthly leave summary for payroll (page + Excel download).
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('/payroll/export', [PayrollController::class, 'export'])->name('payroll.export');

    // Attestation requests and issued attestations.
    Route::get('/attestations', [AttestationController::class, 'index'])->name('attestations.index');

    // HR indicators and the year-end report (Excel).
    Route::get('/indicators', [IndicatorsController::class, 'index'])->name('indicators.index');
    Route::get('/indicators/export', [IndicatorsController::class, 'export'])->name('indicators.export');
});

// Data protection register, access log and retention: Administrateur only.
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin/privacy')->name('admin.privacy.')->group(function () {
    Route::get('/', [PrivacyController::class, 'register'])->name('register');
    Route::get('/log', [PrivacyController::class, 'log'])->name('log');
    Route::post('/retention', [PrivacyController::class, 'applyRetention'])->name('retention');
});

// HR changes: Admin and RH only. Which accounts RH may change is checked in
// AdminController (RH handles plain employees; only Admin gives roles).
Route::middleware(['auth', 'verified', 'role:admin,rh'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/users', [AdminController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminController::class, 'update'])->name('users.update');
    Route::put('/users/{user}/reset-password', [AdminController::class, 'resetPassword'])->name('users.reset-password');
    Route::put('/users/{user}/deactivate', [AdminController::class, 'deactivate'])->name('users.deactivate');
    Route::put('/users/{user}/reactivate', [AdminController::class, 'reactivate'])->name('users.reactivate');
    Route::put('/users/{user}/personal-data', [EmployeeController::class, 'updateProfile'])->name('users.personal-data');

    // Employee document folder (contract, CIN copy, diplomas…).
    Route::post('/users/{user}/documents', [EmployeeDocumentController::class, 'store'])->name('users.documents.store');
    Route::delete('/employee-documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employee-documents.destroy');

    // Employment contracts (history: CDD, renewal, CDI…).
    Route::post('/users/{user}/contracts', [EmploymentContractController::class, 'store'])->name('users.contracts.store');
    Route::put('/contracts/{contract}', [EmploymentContractController::class, 'update'])->name('contracts.update');
    Route::delete('/contracts/{contract}', [EmploymentContractController::class, 'destroy'])->name('contracts.destroy');

    // Import employees from Excel (template, check, import), then invite them.
    Route::get('/import', [EmployeeImportController::class, 'index'])->name('import.index');
    Route::get('/import/modele', [EmployeeImportController::class, 'template'])->name('import.template');
    Route::post('/import', [EmployeeImportController::class, 'upload'])->name('import.upload');
    Route::get('/import/{token}/erreurs', [EmployeeImportController::class, 'report'])->name('import.report');
    Route::post('/import/{token}/confirm', [EmployeeImportController::class, 'confirm'])->name('import.confirm');
    Route::post('/users/invite', [EmployeeImportController::class, 'invite'])->name('users.invite');

    // HR sets a manager's delegation (sudden absence…).
    Route::post('/users/{user}/delegations', [DelegationController::class, 'storeFor'])->name('users.delegations.store');

    // Export of an employee's data on request (right of access).
    Route::get('/users/{user}/data-export', [PrivacyController::class, 'exportFor'])->name('users.data-export');

    // Issuing or refusing attestations (never one's own).
    Route::post('/users/{user}/attestations', [AttestationController::class, 'storeForEmployee'])->name('users.attestations.store');
    Route::put('/attestations/{attestation}/issue', [AttestationController::class, 'issue'])->name('attestations.issue');
    Route::put('/attestations/{attestation}/refuse', [AttestationController::class, 'refuse'])->name('attestations.refuse');

    // Company details printed on attestations.
    Route::get('/company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('/company', [CompanyController::class, 'update'])->name('company.update');
    Route::post('/company/logo', [CompanyController::class, 'uploadLogo'])->name('company.logo.store');
    Route::delete('/company/logo', [CompanyController::class, 'deleteLogo'])->name('company.logo.destroy');

    // Public holidays (not counted as leave days).
    Route::get('/holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
    Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    // Departments employees are attached to.
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
