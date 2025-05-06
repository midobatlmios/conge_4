<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AdminController;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Controllers\DemandesController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/users', [AdminController::class, 'getUsers'])->name('users.index');
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
    Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/mark-as-read', [App\Http\Controllers\NotificationController::class, 'markAsRead']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';