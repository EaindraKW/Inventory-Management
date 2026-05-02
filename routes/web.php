<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('saas.login');
});

Route::get('/app/login', function () {
    return Inertia::render('Saas/Login');
})->name('saas.login');

Route::get('/app/register', function () {
    return Inertia::render('Saas/Register');
})->name('saas.register');

Route::get('/app/dashboard', function () {
    return Inertia::render('Saas/Dashboard');
})->name('saas.dashboard');

Route::get('/app/products', function () {
    return Inertia::render('Saas/Products');
})->name('saas.products');

Route::get('/app/orders', function () {
    return Inertia::render('Saas/Orders');
})->name('saas.orders');

Route::get('/legacy', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
