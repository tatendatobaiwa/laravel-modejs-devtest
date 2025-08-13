<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Standard API rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(env('THROTTLE_REQUESTS_PER_MINUTE', 60))
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many requests. Please try again later.',
                        'error' => 'Rate limit exceeded'
                    ], 429, $headers);
                });
        });

        // Login rate limiting
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(env('THROTTLE_LOGIN_ATTEMPTS', 5))
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many login attempts. Please try again later.',
                        'error' => 'Login rate limit exceeded'
                    ], 429, $headers);
                });
        });

        // Admin operations rate limiting (more restrictive)
        RateLimiter::for('admin', function (Request $request) {
            return [
                // Per-user limit for admin operations
                Limit::perMinute(env('THROTTLE_ADMIN_REQUESTS_PER_MINUTE', 30))
                    ->by('admin:' . ($request->user()?->id ?: $request->ip())),
                
                // Global admin operations limit
                Limit::perMinute(env('THROTTLE_ADMIN_GLOBAL_PER_MINUTE', 100))
                    ->by('admin:global'),
            ];
        });

        // Bulk operations rate limiting (very restrictive)
        RateLimiter::for('bulk', function (Request $request) {
            return Limit::perMinute(env('THROTTLE_BULK_REQUESTS_PER_MINUTE', 5))
                ->by('bulk:' . ($request->user()?->id ?: $request->ip()))
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bulk operations are rate limited. Please wait before trying again.',
                        'error' => 'Bulk operation rate limit exceeded'
                    ], 429, $headers);
                });
        });

        // File upload rate limiting
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(env('THROTTLE_UPLOAD_REQUESTS_PER_MINUTE', 10))
                ->by('upload:' . ($request->user()?->id ?: $request->ip()))
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File upload rate limit exceeded. Please wait before uploading again.',
                        'error' => 'Upload rate limit exceeded'
                    ], 429, $headers);
                });
        });

        // Public registration rate limiting
        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(env('THROTTLE_REGISTRATION_PER_MINUTE', 3))
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Registration rate limit exceeded. Please wait before registering again.',
                        'error' => 'Registration rate limit exceeded'
                    ], 429, $headers);
                });
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}