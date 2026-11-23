<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemandesController;
use App\Http\Controllers\NotificationController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/home', function () {
        return redirect()->route('dashboard');
    });
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::post('/users', [AdminController::class, 'store'])->name('admin.users.store');
    Route::get('/users/list', [AdminController::class, 'getUsers'])->name('users.index');
    Route::put('/users/{id}', [AdminController::class, 'update'])->name('users.update');
    Route::put('/users/{id}/reset-password', [AdminController::class, 'resetPassword'])->name('users.reset-password');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/demandes', [DemandesController::class, 'index'])->name('demandes.index');
    Route::delete('/demandes/{id}', [DemandesController::class, 'destroy'])->name('demandes.destroy');
    Route::post('/demandes', [DemandesController::class, 'store'])->name('demandes.store');
    Route::put('/demandes/{id}', [DemandesController::class, 'update'])->name('demandes.update');
});

// Notification routes
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/mark-as-read', [NotificationController::class, 'markAsRead']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';