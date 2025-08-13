<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Core security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Enhanced Permissions Policy
        $response->headers->set('Permissions-Policy', 
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), ' .
            'accelerometer=(), gyroscope=(), magnetometer=(), midi=(), ' .
            'notifications=(), push=(), speaker=(), vibrate=(), fullscreen=()'
        );
        
        // Content Security Policy
        if ($request->is('api/*')) {
            // Strict CSP for API endpoints
            $response->headers->set('Content-Security-Policy', 
                "default-src 'none'; " .
                "frame-ancestors 'none'; " .
                "base-uri 'none'; " .
                "form-action 'none';"
            );
        } else {
            // CSP for web pages (if serving any)
            $response->headers->set('Content-Security-Policy',
                "default-src 'self'; " .
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
                "style-src 'self' 'unsafe-inline'; " .
                "img-src 'self' data: https:; " .
                "font-src 'self'; " .
                "connect-src 'self'; " .
                "media-src 'self'; " .
                "object-src 'none'; " .
                "child-src 'none'; " .
                "worker-src 'none'; " .
                "frame-ancestors 'none'; " .
                "base-uri 'self'; " .
                "form-action 'self';"
            );
        }

        // HSTS header for HTTPS with enhanced security
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Additional security headers
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        
        // Cache control for sensitive endpoints
        if ($request->is('api/*/admin/*') || $request->is('api/*/auth/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        }

        // Remove server information and version disclosure
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('X-AspNet-Version');
        $response->headers->remove('X-AspNetMvc-Version');
        
        // Add custom security identifier (optional)
        $response->headers->set('X-Security-Headers', 'enabled');

        // Log security header application for monitoring
        if (config('app.debug')) {
            logger()->debug('Security headers applied', [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
            ]);
        }

        return $response;
    }
}