<?php

use App\Http\Controllers\AttestationController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    // The employee's own personal data, read-only (HR edits it on the employee page).
    Route::get('settings/personal-data', [EmployeeController::class, 'own'])->name('personal-data');
    Route::get('settings/documents', [EmployeeDocumentController::class, 'own'])->name('my-documents');
    Route::get('settings/attestations', [AttestationController::class, 'mine'])->name('my-attestations');

    // Calendar subscription links (iCal) for Outlook / Google Calendar.
    Route::get('settings/calendar', [CalendarFeedController::class, 'settings'])->name('calendar-feed.settings');
    Route::post('settings/calendar', [CalendarFeedController::class, 'regenerate'])->name('calendar-feed.regenerate');
    Route::delete('settings/calendar', [CalendarFeedController::class, 'disable'])->name('calendar-feed.disable');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
