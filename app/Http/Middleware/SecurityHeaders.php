<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sensible defaults that do not break Embed Widgets (iframes to YouTube/Vimeo).
 * CSP is production-oriented — local/testing skip CSP so Vite HMR (other origin) works.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), autoplay=*, encrypted-media=*, fullscreen=*, picture-in-picture=*',
        );

        // Production CSP only. Local `composer run dev` loads scripts from the
        // Vite origin (e.g. :5173); a strict script-src 'self' freezes the Player.
        if (
            app()->environment('production')
            && ! $response->headers->has('Content-Security-Policy')
        ) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data: blob: https:; media-src 'self' blob: https:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.youtube.com https://www.youtube-nocookie.com; connect-src 'self' https: wss:; frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com",
            );
        }

        return $response;
    }
}
