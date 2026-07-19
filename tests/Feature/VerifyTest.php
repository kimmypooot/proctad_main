<?php

namespace Tests\Feature;

use App\Models\FieldOffice;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_id_shows_limited_info_without_authentication(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);

        $this->get("/verify/{$member->proctad_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify')
                ->where('result.proctad_id', $member->proctad_id)
                ->where('result.name', $member->name)
                ->where('result.field_office', $office->name)
                // Limited info only: no email, mobile, or agency exposure.
                ->missing('result.email')
                ->missing('result.mobile_number'));
    }

    public function test_legacy_pipe_suffixed_code_is_normalized_and_resolves(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);

        $this->get("/verify/{$member->proctad_id}|attendance")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.proctad_id', $member->proctad_id)
                ->where('code', $member->proctad_id));
    }

    /**
     * A disqualified member's ID still resolves, so the page keys its headline off
     * `result.status` to avoid showing "Verified PROCTAD Member" over one. That only
     * works while the raw status reaches the page.
     */
    public function test_status_is_exposed_so_the_page_can_flag_non_active_members(): void
    {
        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);

        foreach (['active', 'inactive', 'disqualified'] as $status) {
            $member = Member::factory()->create([
                'field_office_id' => $office->id,
                'status' => $status,
            ]);

            $this->get("/verify/{$member->proctad_id}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('result.status', $status)
                    ->has('result.status_label')
                    ->has('result.status_variant'));
        }
    }

    /**
     * Certificate numbers are sequential, so an unthrottled public lookup lets
     * anyone walk the range and harvest every releasee's details.
     */
    public function test_public_verification_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/verify/PROCTAD-CSCRO8-XXXXXX')->assertOk();
        }

        $this->get('/verify/PROCTAD-CSCRO8-XXXXXX')->assertStatus(429);
    }

    public function test_unknown_or_removed_id_shows_invalid_state(): void
    {
        $this->get('/verify/PROCTAD-CSCRO8-XXXXXX')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('result', null));

        $office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $member = Member::factory()->create(['field_office_id' => $office->id]);
        $member->delete();

        $this->get("/verify/{$member->proctad_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('result', null));
    }
}
