<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Branded error pages replace Laravel's stock ones, which members would
 * otherwise meet on a public government site with no styling and no way back.
 *
 * Every case here disables debug first. The handler deliberately stands aside
 * while debug is on so developers keep their stack trace — and since APP_DEBUG
 * is true in this environment, a test that forgets this passes without ever
 * reaching the code it claims to cover.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    private function productionErrorHandling(): void
    {
        config(['app.debug' => false]);
    }

    /** Unmatched URLs are caught by the fallback route in routes/web.php. */
    public function test_an_unmatched_url_renders_the_branded_404(): void
    {
        $this->productionErrorHandling();

        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('NotFound'));
    }

    /**
     * A thrown 404 — abort(404), a failed model binding — never reaches the
     * fallback route, so it used to land on the stock page. It now arrives at
     * the same NotFound page as an unmatched URL.
     */
    public function test_a_thrown_404_renders_the_same_page_as_an_unmatched_url(): void
    {
        $this->productionErrorHandling();

        // MemberRequirementController::download aborts 404 on an unknown key.
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $member = \App\Models\Member::factory()->create();

        $this->actingAs($admin)
            ->get("/members/{$member->id}/requirements/not-a-real-requirement/download")
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('NotFound'));
    }

    public function test_a_forbidden_page_renders_the_branded_403(): void
    {
        $this->productionErrorHandling();

        // A member has no access to the staff member registry.
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->get('/members')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/Error')
                ->where('status', 403));
    }

    /**
     * A stale CSRF token usually means a form sat open too long. The page they
     * wanted still works, so send them back to it with an explanation rather
     * than to an error page.
     */
    /**
     * Driven through the exception handler rather than an HTTP request:
     * ValidateCsrfToken short-circuits whenever runningUnitTests() is true, so
     * a genuine 419 cannot be produced from the test client at all.
     */
    public function test_an_expired_session_sends_the_user_back_with_a_message(): void
    {
        $this->productionErrorHandling();

        $session = $this->app['session']->driver();
        $session->setPreviousUrl(url('/my/assignments'));

        $request = \Illuminate\Http\Request::create('/my/assignments', 'POST');
        $request->setLaravelSession($session);

        $response = $this->app[\Illuminate\Contracts\Debug\ExceptionHandler::class]
            ->render($request, new \Illuminate\Session\TokenMismatchException);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(url('/my/assignments'), $response->headers->get('Location'));
        $this->assertNotNull($session->get('error'));
    }

    /** JSON callers want a status, not a rendered page. */
    public function test_json_requests_still_get_json(): void
    {
        $this->productionErrorHandling();

        $this->getJson('/no-such-page')->assertNotFound();
    }

    /** Developers keep their stack trace. */
    public function test_debug_mode_is_left_alone(): void
    {
        config(['app.debug' => true]);

        $this->get('/no-such-page')->assertNotFound();
    }
}
