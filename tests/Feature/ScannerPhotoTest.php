<?php

namespace Tests\Feature;

use App\Enums\ExamRole;
use App\Enums\UserRole;
use App\Http\Controllers\ScannerController;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\ExaminationSchool;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\ScannerSession;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The ID photo on the public scanner.
 *
 * A name and an ID number confirm the code, not the person holding it — but the
 * link this hangs off is shared around a venue, so the fencing is the feature:
 * the URL is signed, it expires in minutes, and it is refused for anyone
 * outside the session's own roster even when the signature is good.
 */
class ScannerPhotoTest extends TestCase
{
    use RefreshDatabase;

    private FieldOffice $office;

    private Examination $exam;

    private ExaminationSchool $venue;

    private ScannerSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->office = FieldOffice::create(['name' => 'Leyte Field Office', 'code' => 'LEY']);
        $this->exam = Examination::factory()->create(['exam_date' => now()]);
        $this->venue = ExaminationSchool::factory()->create([
            'examination_id' => $this->exam->id,
            'school_id' => School::factory()->forFieldOffice($this->office->id)->create(),
        ]);
        $this->session = ScannerSession::create([
            'token' => ScannerSession::generateToken(),
            'label' => 'Main gate',
            'examination_id' => $this->exam->id,
            'examination_school_id' => $this->venue->id,
            'field_office_id' => $this->office->id,
            'created_by' => User::factory()->create(['role' => UserRole::FoAdmin, 'field_office_id' => $this->office->id])->id,
            'expires_at' => now()->addHours(8),
        ]);
    }

    private function deployedMember(bool $withPhoto = true): Member
    {
        $member = Member::factory()->create([
            'field_office_id' => $this->office->id,
            'photo_path' => $withPhoto ? UploadedFile::fake()->image('id.jpg')->store('member-photos', 'local') : null,
        ]);

        ExamAssignment::factory()->create([
            'examination_id' => $this->exam->id,
            'examination_school_id' => $this->venue->id,
            'member_id' => $member->id,
            'field_office_id' => $this->office->id,
            'testing_center_id' => $member->testing_center_id,
            'role' => ExamRole::Proctor->value,
        ]);

        return $member;
    }

    private function photoUrlFromScan(Member $member): ?string
    {
        $url = null;

        $this->get("/scan/{$this->session->token}?code={$member->proctad_id}")
            ->assertOk()
            ->assertInertia(function ($page) use (&$url) {
                $url = $page->toArray()['props']['result']['photo_url'] ?? null;
            });

        return $url;
    }

    public function test_a_public_scan_hands_back_a_signed_photo_url_that_works(): void
    {
        $member = $this->deployedMember();

        $url = $this->photoUrlFromScan($member);

        $this->assertNotNull($url);
        $this->assertStringContainsString('signature=', $url);
        $this->get($url)->assertOk();
    }

    /** No photo on file means no URL, so the card falls back to initials rather than a broken image. */
    public function test_no_url_is_minted_for_a_member_without_a_photo(): void
    {
        $member = $this->deployedMember(withPhoto: false);

        $this->assertNull($this->photoUrlFromScan($member));
    }

    public function test_an_unsigned_url_is_refused(): void
    {
        $member = $this->deployedMember();

        $this->get("/scan/{$this->session->token}/photo/member/{$member->id}")
            ->assertForbidden();
    }

    public function test_the_url_stops_working_once_it_expires(): void
    {
        $member = $this->deployedMember();
        $url = $this->photoUrlFromScan($member);

        $this->travel(ScannerController::PHOTO_LINK_TTL_MINUTES + 1)->minutes();

        $this->get($url)->assertForbidden();
    }

    /**
     * Signature good, roster wrong. Without this check a link issued for one
     * examination would be a way to read the photo of any member in the region.
     */
    public function test_a_valid_signature_is_still_refused_for_someone_not_on_this_roster(): void
    {
        $onRoster = $this->deployedMember();
        $stranger = Member::factory()->create([
            'field_office_id' => $this->office->id,
            'photo_path' => UploadedFile::fake()->image('other.jpg')->store('member-photos', 'local'),
        ]);

        // Take a legitimately signed URL and point it at somebody else by
        // swapping only the member id — the signature no longer covers it.
        $tampered = str_replace(
            "/photo/member/{$onRoster->id}",
            "/photo/member/{$stranger->id}",
            $this->photoUrlFromScan($onRoster),
        );

        $this->get($tampered)->assertForbidden();
    }

    /** Staff read photos through the ordinary member record, so the scan payload adds nothing for them. */
    public function test_the_staff_scanner_payload_carries_no_photo_url(): void
    {
        $member = $this->deployedMember();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)
            ->get("/scanner?code={$member->proctad_id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('result.photo_url'));
    }
}
