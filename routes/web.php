<?php

use App\Http\Controllers\CullController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EggProductionController;
use App\Http\Controllers\EggSaleController;
use App\Http\Controllers\LivestockController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/dashboard/ai-insight', [DashboardController::class, 'aiInsight'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.ai-insight');

Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Egg Production
    Route::get('/production', [EggProductionController::class, 'index'])->name('productions.index');
    Route::post('/production', [EggProductionController::class, 'store'])->name('productions.store');
    Route::get('/production/{production}/edit', [EggProductionController::class, 'edit'])->name('productions.edit');
    Route::patch('/production/{production}', [EggProductionController::class, 'update'])->name('productions.update');
    Route::delete('/production/{production}', [EggProductionController::class, 'destroy'])->name('productions.destroy');

    // Egg Sales
    Route::get('/sales', [EggSaleController::class, 'index'])->name('sales.index');
    Route::post('/sales', [EggSaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale}/edit', [EggSaleController::class, 'edit'])->name('sales.edit');
    Route::patch('/sales/{sale}', [EggSaleController::class, 'update'])->name('sales.update');
    Route::delete('/sales/{sale}', [EggSaleController::class, 'destroy'])->name('sales.destroy');

    // Cull Chickens
    Route::get('/cull', [CullController::class, 'index'])->name('cull.index');
    Route::post('/cull', [CullController::class, 'store'])->name('cull.store');
    Route::delete('/cull/{cullRecord}', [CullController::class, 'destroy'])->name('cull.destroy');

    // Livestock Records
    Route::get('/livestock', [LivestockController::class, 'index'])->name('livestock.index');
    Route::post('/livestock/hens', [LivestockController::class, 'storeHen'])->name('livestock.hens.store');
    Route::patch('/livestock/hens/{henBatch}', [LivestockController::class, 'updateHen'])->name('livestock.hens.update');
    Route::delete('/livestock/hens/{henBatch}', [LivestockController::class, 'destroyHen'])->name('livestock.hens.destroy');
    Route::post('/livestock/cattle', [LivestockController::class, 'storeCattle'])->name('livestock.cattle.store');
    Route::patch('/livestock/cattle/{cattleRecord}', [LivestockController::class, 'updateCattle'])->name('livestock.cattle.update');
    Route::delete('/livestock/cattle/{cattleRecord}', [LivestockController::class, 'destroyCattle'])->name('livestock.cattle.destroy');

    // Anomaly alert actions (admin + manager)
    Route::middleware('role:admin,manager')->group(function () {
        Route::patch('/alerts/{alert}/reviewed', [DashboardController::class, 'markReviewed'])->name('alerts.reviewed');
        Route::patch('/alerts/{alert}/resolved', [DashboardController::class, 'markResolved'])->name('alerts.resolved');
    });

    // Reports (admin + manager only)
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/pdf', [ReportsController::class, 'exportPdf'])->name('reports.export.pdf');
        Route::get('/reports/export/csv', [ReportsController::class, 'exportCsv'])->name('reports.export.csv');
    });

    // Users (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/auth.php';
