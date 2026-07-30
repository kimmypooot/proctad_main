<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The application shipped with none of these, which left the console framable,
 * uploaded photos exposed to MIME sniffing, and the signed assignment links —
 * which are bearer credentials — leaking in the Referer header.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0: string, 1: string}> */
    public static function expectedHeaders(): array
    {
        return [
            ['X-Frame-Options', 'DENY'],
            ['X-Content-Type-Options', 'nosniff'],
            ['Referrer-Policy', 'strict-origin-when-cross-origin'],
            ['Cross-Origin-Opener-Policy', 'same-origin'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('expectedHeaders')]
    public function test_public_pages_carry_the_header(string $header, string $value): void
    {
        $this->get('/')->assertOk()->assertHeader($header, $value);
    }

    public function test_the_content_security_policy_forbids_framing_and_foreign_script(): void
    {
        $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        // No escape hatch on script-src. The one inline block the layout emits
        // (the JSON-LD schema) is carried by a per-response nonce instead, so
        // 'unsafe-inline' is never needed and the policy keeps its teeth.
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    /**
     * The inline JSON-LD block must carry the same nonce the header advertises,
     * or the browser drops the structured data. Regression guard: the nonce is
     * generated in the middleware before the view renders, and it is easy to
     * break that ordering without noticing, because nothing visible changes.
     */
    public function test_the_inline_json_ld_block_carries_the_advertised_nonce(): void
    {
        $response = $this->get('/')->assertOk();

        preg_match("/script-src 'self' 'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $header);
        preg_match('/<script type="application\/ld\+json" nonce="([^"]+)"/', $response->getContent(), $markup);

        $this->assertNotEmpty($header[1] ?? null, 'No nonce in the CSP header.');
        $this->assertSame($header[1], $markup[1] ?? null);
    }

    /**
     * During `npm run dev` the bundles come from a separate origin and HMR
     * opens a websocket back to it. A policy built only for the production
     * build blocks both, and the app renders as a blank page — which is exactly
     * what happened when this middleware first shipped.
     */
    public function test_the_vite_dev_server_is_allowed_when_one_is_running(): void
    {
        $hotFile = public_path('hot');
        $existing = is_file($hotFile) ? file_get_contents($hotFile) : null;
        file_put_contents($hotFile, 'http://localhost:5173');

        try {
            $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

            $this->assertStringContainsString('http://localhost:5173', $csp);
            $this->assertStringContainsString('ws://localhost:5173', $csp);
        } finally {
            $existing === null ? @unlink($hotFile) : file_put_contents($hotFile, $existing);
        }
    }

    /** The dev server must never appear in a production policy. */
    public function test_no_dev_origin_leaks_without_a_running_dev_server(): void
    {
        $hotFile = public_path('hot');
        $existing = is_file($hotFile) ? file_get_contents($hotFile) : null;
        @unlink($hotFile);

        try {
            $this->assertStringNotContainsString(
                'localhost:5173',
                $this->get('/')->headers->get('Content-Security-Policy'),
            );
        } finally {
            if ($existing !== null) {
                file_put_contents($hotFile, $existing);
            }
        }
    }

    /** Report-only is the deployment ramp, and must be switchable. */
    public function test_report_only_mode_uses_the_report_only_header(): void
    {
        config(['security.csp_report_only' => true]);

        $this->get('/')
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    /**
     * Error and maintenance responses are exactly what an attacker probes, so
     * the middleware runs before anything that can short-circuit the stack.
     */
    public function test_error_responses_carry_the_headers_too(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_authenticated_pages_carry_the_headers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    /**
     * The certificate modals render the PDF in an iframe on this origin. A
     * blanket DENY / frame-ancestors 'none' turned every preview into "refused
     * to connect", so the two PDF responses — and only those — allow 'self'.
     */
    public function test_the_certificate_pdf_responses_may_be_framed_by_the_app_itself(): void
    {
        Storage::fake('local');

        $assignment = ExamAssignment::factory()->create();

        $certificate = Certificate::create([
            'type' => CertificateType::Appearance,
            'member_id' => $assignment->member_id,
            'field_office_id' => $assignment->field_office_id,
            'certifiable_type' => ExamAssignment::class,
            'certifiable_id' => $assignment->id,
            'status' => CertificateStatus::Released,
            'certificate_no' => 'RO8-COA-2026-00001',
            'pdf_path' => 'certificates/RO8-COA-2026-00001.pdf',
        ]);

        Storage::disk('local')->put($certificate->pdf_path, '%PDF-1.4');

        $response = $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->get("/certificates/{$certificate->id}/view")
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $this->assertStringContainsString(
            "frame-ancestors 'self'",
            $response->headers->get('Content-Security-Policy'),
        );
    }

    /**
     * HSTS is meaningless over plain HTTP and actively harmful from a local
     * dev host, which is why it is conditional on the request being secure.
     */
    public function test_hsts_is_not_emitted_over_plain_http(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }
}
