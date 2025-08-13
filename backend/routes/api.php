<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;

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

// Health Check - Public endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'healthy', 'timestamp' => now()]);
});

// API Version 1 Routes
Route::prefix('v1')->name('api.v1.')->group(function () {
    
    // Authentication Routes (public)
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');
    });

    // Public Routes (no authentication required)
    Route::prefix('public')->name('public.')->group(function () {
        // User registration endpoint with rate limiting
        Route::post('users', [UserController::class, 'store'])
            ->middleware('throttle:registration')
            ->name('users.store');
        
        // System information
        Route::get('info', function () {
            return response()->json([
                'api_version' => '1.0',
                'laravel_version' => app()->version(),
                'environment' => config('app.env'),
                'timestamp' => now()
            ]);
        })->name('info');
    });

    // Authenticated Routes (require valid token)
    Route::middleware('auth:sanctum')->group(function () {
        
        // Authentication management
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::get('tokens', [AuthController::class, 'tokens'])->name('tokens');
            Route::delete('tokens/{token_id}', [AuthController::class, 'revokeToken'])->name('revoke-token');
        });

        // Current user endpoint (legacy support)
        Route::get('user', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => $request->user()->load(['salary', 'uploadedDocuments'])
            ]);
        })->name('user');

        // User Management Routes (basic authenticated users)
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/{user}', [UserController::class, 'show'])->name('show');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
        });

        // Salary Management Routes (basic authenticated users)
        Route::prefix('salaries')->name('salaries.')->group(function () {
            Route::get('/{salary}', [SalaryController::class, 'show'])->name('show');
        });

        // Admin Protected Routes (require admin role)
        Route::middleware(['admin', 'throttle:admin'])->prefix('admin')->name('admin.')->group(function () {
            
            // Admin Dashboard
            Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('statistics', [AdminController::class, 'statistics'])->name('statistics');
            Route::get('health', [AdminController::class, 'health'])->name('health');

            // User Management (Admin)
            Route::prefix('users')->name('users.')->group(function () {
                Route::get('/', [UserController::class, 'index'])->name('index');
                Route::get('/{user}', [UserController::class, 'show'])->name('show');
                Route::put('/{user}', [UserController::class, 'update'])->name('update');
                Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
                Route::post('/bulk-update', [UserController::class, 'bulkUpdate'])
                    ->middleware('throttle:bulk')
                    ->name('bulk-update');
                Route::get('/{user}/history', [UserController::class, 'history'])->name('history');
            });

            // Salary Management (Admin)
            Route::prefix('salaries')->name('salaries.')->group(function () {
                Route::get('/', [SalaryController::class, 'index'])->name('index');
                Route::post('/', [SalaryController::class, 'store'])->name('store');
                Route::get('/{salary}', [SalaryController::class, 'show'])->name('show');
                Route::put('/{salary}', [SalaryController::class, 'update'])->name('update');
                Route::delete('/{salary}', [SalaryController::class, 'destroy'])->name('destroy');
                Route::get('/{salary}/history', [SalaryController::class, 'history'])->name('history');
            });

            // Commission Management (Admin)
            Route::prefix('commissions')->name('commissions.')->group(function () {
                Route::get('/', [CommissionController::class, 'index'])->name('index');
                Route::put('/', [CommissionController::class, 'update'])->name('update');
                Route::get('/history', [CommissionController::class, 'history'])->name('history');
            });

            // Advanced Admin Operations
            Route::get('users', [AdminController::class, 'users'])->name('users.advanced');
        });
    });
});

// API Version 2 Routes (Future extensibility)
Route::prefix('v2')->name('api.v2.')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'message' => 'API Version 2 - Coming Soon',
            'version' => '2.0',
            'status' => 'development'
        ]);
    });
});

// Route Model Binding Configuration
Route::bind('user', function ($value) {
    return \App\Models\User::where('id', $value)->firstOrFail();
});

Route::bind('salary', function ($value) {
    return \App\Models\Salary::where('id', $value)->firstOrFail();
});

Route::bind('commission', function ($value) {
    return \App\Models\Commission::where('id', $value)->firstOrFail();
});

Route::bind('document', function ($value) {
    return \App\Models\UploadedDocument::where('id', $value)->firstOrFail();
});
