<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemandesController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
});

// Employee account management.
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminController::class, 'users'])->name('users.index');
    Route::post('/users', [AdminController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminController::class, 'update'])->name('users.update');
    Route::put('/users/{user}/reset-password', [AdminController::class, 'resetPassword'])->name('users.reset-password');
    Route::put('/users/{user}/deactivate', [AdminController::class, 'deactivate'])->name('users.deactivate');
    Route::put('/users/{user}/reactivate', [AdminController::class, 'reactivate'])->name('users.reactivate');

    // Public holidays (not counted as leave days).
    Route::get('/holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
    Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
