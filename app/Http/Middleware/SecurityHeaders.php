<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Suppress X-Powered-By at the PHP level (before response is built)
        header_remove('X-Powered-By');

        $response = $next($request);

        // Fix #1: Content Security Policy — prevents XSS & data injection
        // Allows self-hosted assets only; extend with CDN origins if needed
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
                "script-src 'self'; " .
                "style-src 'self' 'unsafe-inline'; " .
                "img-src 'self' data:; " .
                "font-src 'self'; " .
                "connect-src 'self'; " .
                "frame-ancestors 'none'; " .
                "form-action 'self'; " .
                "base-uri 'self';"
        );

        // Fix #2: Anti-clickjacking — redundant with CSP frame-ancestors but kept for older browsers
        $response->headers->set('X-Frame-Options', 'DENY');

        // Fix #3: Prevents MIME-type sniffing attacks
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Fix #4: Controls how much referrer info is sent
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Fix #5: Restrict browser features
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');

        // Fix #6: Force HTTPS for 1 year (only effective over HTTPS)
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // Fix #7 & #8: Remove server information leakage headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
