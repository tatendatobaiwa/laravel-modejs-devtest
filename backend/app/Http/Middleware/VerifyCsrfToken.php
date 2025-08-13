<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // API routes are protected by Sanctum tokens instead of CSRF
        'api/*',
        // Health check endpoint
        'health',
    ];

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        // Always verify CSRF for state-changing operations unless explicitly excluded
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Log CSRF verification attempts for security monitoring
            if (!parent::inExceptArray($request)) {
                logger()->info('CSRF verification required', [
                    'url' => $request->url(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        return parent::inExceptArray($request);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Add additional security logging for CSRF failures
        try {
            return parent::handle($request, $next);
        } catch (\Illuminate\Session\TokenMismatchException $e) {
            // Log CSRF token mismatch for security monitoring
            logger()->warning('CSRF token mismatch detected', [
                'url' => $request->url(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
                'session_id' => $request->session()->getId(),
            ]);

            throw $e;
        }
    }

    /**
     * Add the CSRF token to the response cookies.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addCookieToResponse($request, $response)
    {
        $response = parent::addCookieToResponse($request, $response);

        // Ensure CSRF cookie has secure attributes
        if ($request->secure()) {
            $config = config('session');
            
            $response->headers->setCookie(
                cookie(
                    'XSRF-TOKEN',
                    $request->session()->token(),
                    $config['lifetime'],
                    $config['path'],
                    $config['domain'],
                    $config['secure'],
                    false, // HttpOnly should be false for CSRF token to be accessible by JS
                    false,
                    $config['same_site'] ?? 'lax'
                )
            );
        }

        return $response;
    }
}