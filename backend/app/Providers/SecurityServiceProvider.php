<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Validator;
use App\Services\InputSanitizationService;

class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register input sanitization service
        $this->app->singleton(InputSanitizationService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureCustomValidationRules();
        $this->configureSecurityHeaders();
    }

    /**
     * Configure comprehensive rate limiting with Redis backend.
     */
    protected function configureRateLimiting(): void
    {
        // Enhanced API rate limiting with Redis
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();
            $limit = (int) config('app.throttle.api_requests_per_minute', 60);
            
            return Limit::perMinute($limit)
                ->by("api:{$key}")
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many requests. Please try again later.',
                        'error' => 'Rate limit exceeded',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Strict login rate limiting
        RateLimiter::for('login', function (Request $request) {
            $attempts = (int) config('app.throttle.login_attempts', 5);
            
            return [
                // Per IP limit
                Limit::perMinute($attempts)
                    ->by("login:ip:{$request->ip()}")
                    ->response(function (Request $request, array $headers) {
                        logger()->warning('Login rate limit exceeded', [
                            'ip' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'attempted_email' => $request->input('email'),
                        ]);
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Too many login attempts. Please try again later.',
                            'error' => 'Login rate limit exceeded',
                            'retry_after' => $headers['Retry-After'] ?? 60,
                        ], 429, $headers);
                    }),
                
                // Per email limit (if provided)
                $request->filled('email') ? 
                    Limit::perMinute($attempts * 2)
                        ->by("login:email:" . strtolower($request->input('email')))
                        ->response(function (Request $request, array $headers) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Too many attempts for this email. Please try again later.',
                                'error' => 'Email rate limit exceeded',
                                'retry_after' => $headers['Retry-After'] ?? 60,
                            ], 429, $headers);
                        }) : null,
            ];
        });

        // Admin operations rate limiting
        RateLimiter::for('admin', function (Request $request) {
            $userLimit = (int) config('app.throttle.admin_requests_per_minute', 30);
            $globalLimit = (int) config('app.throttle.admin_global_per_minute', 100);
            
            return [
                // Per admin user limit
                Limit::perMinute($userLimit)
                    ->by("admin:user:" . ($request->user()?->id ?: $request->ip()))
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Admin rate limit exceeded. Please slow down.',
                            'error' => 'Admin rate limit exceeded',
                            'retry_after' => $headers['Retry-After'] ?? 60,
                        ], 429, $headers);
                    }),
                
                // Global admin operations limit
                Limit::perMinute($globalLimit)
                    ->by('admin:global')
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'success' => false,
                            'message' => 'System admin limit reached. Please try again later.',
                            'error' => 'Global admin rate limit exceeded',
                            'retry_after' => $headers['Retry-After'] ?? 60,
                        ], 429, $headers);
                    }),
            ];
        });

        // Bulk operations rate limiting (very restrictive)
        RateLimiter::for('bulk', function (Request $request) {
            $limit = (int) config('app.throttle.bulk_requests_per_minute', 5);
            
            return Limit::perMinute($limit)
                ->by("bulk:" . ($request->user()?->id ?: $request->ip()))
                ->response(function (Request $request, array $headers) {
                    logger()->info('Bulk operation rate limited', [
                        'user_id' => $request->user()?->id,
                        'ip' => $request->ip(),
                        'endpoint' => $request->path(),
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Bulk operations are rate limited. Please wait before trying again.',
                        'error' => 'Bulk operation rate limit exceeded',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // File upload rate limiting
        RateLimiter::for('uploads', function (Request $request) {
            $limit = (int) config('app.throttle.upload_requests_per_minute', 10);
            
            return Limit::perMinute($limit)
                ->by("upload:" . ($request->user()?->id ?: $request->ip()))
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File upload rate limit exceeded. Please wait before uploading again.',
                        'error' => 'Upload rate limit exceeded',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Registration rate limiting
        RateLimiter::for('registration', function (Request $request) {
            $limit = (int) config('app.throttle.registration_per_minute', 3);
            
            return Limit::perMinute($limit)
                ->by("registration:" . $request->ip())
                ->response(function (Request $request, array $headers) {
                    logger()->info('Registration rate limited', [
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Registration rate limit exceeded. Please wait before registering again.',
                        'error' => 'Registration rate limit exceeded',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Password reset rate limiting
        RateLimiter::for('password-reset', function (Request $request) {
            return [
                // Per IP limit
                Limit::perMinute(3)
                    ->by("password-reset:ip:" . $request->ip()),
                
                // Per email limit
                $request->filled('email') ?
                    Limit::perMinute(2)
                        ->by("password-reset:email:" . strtolower($request->input('email'))) : null,
            ];
        });
    }

    /**
     * Configure custom validation rules for enhanced security.
     */
    protected function configureCustomValidationRules(): void
    {
        // Custom validation rule for suspicious input detection
        Validator::extend('no_suspicious_patterns', function ($attribute, $value, $parameters, $validator) {
            $sanitizer = app(InputSanitizationService::class);
            
            if ($sanitizer->containsSuspiciousPatterns($value)) {
                $sanitizer->logSuspiciousInput($value, "Validation rule: {$attribute}");
                return false;
            }
            
            return true;
        });

        // Custom validation rule for safe file names
        Validator::extend('safe_filename', function ($attribute, $value, $parameters, $validator) {
            // Check for dangerous file name patterns
            $dangerousPatterns = [
                '/\.\.(\/|\\)/',  // Directory traversal
                '/[<>:"|?*]/',    // Invalid filename characters
                '/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$/i', // Windows reserved names
                '/^\./,           // Hidden files starting with dot
            ];

            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return false;
                }
            }

            return true;
        });

        // Custom validation rule for strong passwords
        Validator::extend('strong_password', function ($attribute, $value, $parameters, $validator) {
            // At least 8 characters, with uppercase, lowercase, number, and special character
            return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $value);
        });

        // Custom validation rule for business email domains
        Validator::extend('business_email', function ($attribute, $value, $parameters, $validator) {
            $restrictedDomains = [
                'tempmail.com', '10minutemail.com', 'guerrillamail.com',
                'mailinator.com', 'throwaway.email', 'temp-mail.org',
                'getnada.com', 'maildrop.cc', 'sharklasers.com'
            ];

            $domain = substr(strrchr($value, "@"), 1);
            return !in_array(strtolower($domain), $restrictedDomains);
        });

        // Custom validation messages
        Validator::replacer('no_suspicious_patterns', function ($message, $attribute, $rule, $parameters) {
            return "The {$attribute} contains invalid characters or patterns.";
        });

        Validator::replacer('safe_filename', function ($message, $attribute, $rule, $parameters) {
            return "The {$attribute} contains invalid filename characters.";
        });

        Validator::replacer('strong_password', function ($message, $attribute, $rule, $parameters) {
            return "The {$attribute} must be at least 8 characters and contain uppercase, lowercase, number, and special character.";
        });

        Validator::replacer('business_email', function ($message, $attribute, $rule, $parameters) {
            return "The {$attribute} must be from a valid business domain.";
        });
    }

    /**
     * Configure additional security headers.
     */
    protected function configureSecurityHeaders(): void
    {
        // This is handled by SecurityHeadersMiddleware, but we can add
        // additional configuration here if needed
        
        // Set secure session configuration
        if (config('session.driver') === 'redis') {
            config([
                'session.secure' => request()->secure(),
                'session.http_only' => true,
                'session.same_site' => 'lax',
            ]);
        }
    }
}