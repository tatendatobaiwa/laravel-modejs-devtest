<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\CommissionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// User Management Routes
Route::prefix('users')->group(function () {
    Route::post('/', [UserController::class, 'store']); // Register user
    Route::get('/', [UserController::class, 'index']); // List all users (admin)
    Route::get('/{user}', [UserController::class, 'show']); // Get user details
    Route::put('/{user}', [UserController::class, 'update']); // Update user
    Route::delete('/{user}', [UserController::class, 'destroy']); // Delete user
    Route::post('/bulk-update', [UserController::class, 'bulkUpdate']); // Bulk update
});

// Salary Management Routes
Route::prefix('salary')->group(function () {
    Route::get('/', [SalaryController::class, 'index']); // List salaries
    Route::post('/', [SalaryController::class, 'store']); // Create salary record
    Route::get('/{salary}', [SalaryController::class, 'show']); // Get salary details
    Route::put('/{salary}', [SalaryController::class, 'update']); // Update salary
    Route::delete('/{salary}', [SalaryController::class, 'destroy']); // Delete salary
});

// Commission Management Routes
Route::prefix('commission')->group(function () {
    Route::get('/', [CommissionController::class, 'index']); // Get commission settings
    Route::put('/', [CommissionController::class, 'update']); // Update commission
});

// Health Check
Route::get('/health', function () {
    return response()->json(['status' => 'healthy', 'timestamp' => now()]);
});
