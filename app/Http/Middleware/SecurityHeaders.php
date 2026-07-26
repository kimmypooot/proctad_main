<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side hardening for every response.
 *
 * The application had none of these, which left the whole authenticated
 * console framable (clickjacking against bulk-approve and bulk-revoke), left
 * uploaded member photos exposed to MIME sniffing, and let the signed
 * assignment-confirmation links — which are bearer credentials — leak in the
 * Referer header to any external site.
 *
 * Applied first in the web group, before CheckMaintenanceMode, so the headers
 * are present on the 503 maintenance notice and on error pages too. Those are
 * exactly the responses an attacker probes for, and the ones a header
 * middleware placed further down would miss.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generated before the response renders, so Blade can stamp it on the
        // inline JSON-LD block and Vite can stamp it on the tags it emits.
        // A nonce is what lets script-src stay free of 'unsafe-inline'.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->add([
            // The console must never be framed: /approvals and the bulk
            // certificate actions are one click each.
            'X-Frame-Options' => 'DENY',
            // Stops a polyglot upload served inline from members.photo being
            // sniffed as HTML and executed on this origin.
            'X-Content-Type-Options' => 'nosniff',
            // Signed links live in the URL; do not hand them to third parties.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // The scanner needs the camera. Nothing else needs anything.
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);

        $response->headers->set(
            config('security.csp_report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy',
            $this->contentSecurityPolicy($nonce),
        );

        // Only meaningful over TLS, and actively harmful if emitted from a
        // plain-HTTP development host: the browser would pin localhost to
        // HTTPS for a year. Requires trusted proxies to be configured, or
        // secure() reads false behind a load balancer (see config/security.php).
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }

    /**
     * A production build ships hashed, self-hosted bundles, so script-src stays
     * at 'self' plus a per-response nonce for the one inline block the layout
     * emits (the JSON-LD organisation schema) — no 'unsafe-inline' anywhere,
     * which is what makes the policy worth having.
     *
     * style-src keeps 'unsafe-inline' because Vue's scoped styles and the
     * inline style bindings across the Inertia pages both emit them; tightening
     * that to a nonce is a follow-up, not a blocker.
     */
    private function contentSecurityPolicy(string $nonce): string
    {
        $allowed = config('security.csp_allowed');

        // The directives the dev server actually serves: modules, the
        // stylesheet, the HMR websocket, and — via Vite::fonts() — the font
        // files, which it rewrites to /__laravel_vite_plugin__/fonts/ on its
        // own origin. Images too, for assets imported from a component.
        // Empty in production, so the deployed policy is unaffected.
        $dev = $this->devServerOrigins();
        $devFor = fn (string $directive) => in_array($directive, ['script', 'style', 'connect', 'font', 'img'], true)
            ? $dev
            : [];

        $join = fn (string $key, string ...$base) => implode(' ', array_filter([
            ...$base,
            ...($allowed[$key] ?? []),
            ...$devFor($key),
        ]));

        return implode('; ', [
            "default-src 'self'",
            $join('script', "script-src 'self' 'nonce-{$nonce}'"),
            $join('style', "style-src 'self' 'unsafe-inline'"),
            $join('font', "font-src 'self' data:"),
            // data: covers the QR codes, which are generated as data URIs
            // (see App\Support\BrandedQrCode::dataUri) and never fetched.
            $join('img', "img-src 'self' data: blob:"),
            "media-src 'self'",
            $join('connect', "connect-src 'self'"),
            // The PDF previews are rendered into an iframe from this origin.
            "frame-src 'self' blob:",
            "worker-src 'self'",
            "manifest-src 'self'",
            // Belt and braces with X-Frame-Options, for browsers that honour
            // only one of the two.
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }

    /**
     * The Vite dev server, when one is running.
     *
     * During `npm run dev` the bundles are served from a separate origin
     * (public/hot holds its URL) and HMR opens a websocket back to it. A policy
     * built only for the production build blocks both, and the app renders as a
     * blank page with nothing but console errors to say why.
     *
     * Returns nothing in production, so the deployed policy is unaffected —
     * public/hot is a development artifact and is gitignored.
     */
    private function devServerOrigins(): array
    {
        $hotFile = public_path('hot');

        if (app()->isProduction() || ! is_file($hotFile)) {
            return [];
        }

        $url = trim((string) file_get_contents($hotFile));

        if ($url === '') {
            return [];
        }

        return [$url, str_replace(['http://', 'https://'], ['ws://', 'wss://'], $url)];
    }
}
