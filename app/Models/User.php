<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'name', 'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'username', 'mobile_number',
    'password', 'must_change_password', 'role', 'is_active', 'field_office_id',
])]
#[Hidden(['password', 'remember_token'])]
#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function fieldOffice(): BelongsTo
    {
        return $this->belongsTo(FieldOffice::class);
    }

    /**
     * Testing centers this staff account operates at. A user's field office
     * handles several centers, and a user may work across more than one, so the
     * link is many-to-many (see the testing_center_user pivot).
     */
    public function testingCenters(): BelongsToMany
    {
        return $this->belongsToMany(TestingCenter::class)->withTimestamps();
    }

    /**
     * Re-derive the testing-center pivot from this user's field office: they
     * operate at every center the office handles. Shared by UserObserver (on
     * every save) and the proctad:resync-user-testing-centers command (which
     * catches existing users up after an office gains a new center). Regional
     * staff with no field office are cleared out.
     *
     * @return array{attached: list<mixed>, detached: list<mixed>, updated: list<mixed>}
     */
    public function syncTestingCentersFromFieldOffice(): array
    {
        $centerIds = $this->field_office_id
            ? DB::table('field_office_testing_center')
                ->where('field_office_id', $this->field_office_id)
                ->pluck('testing_center_id')
            : [];

        return $this->testingCenters()->sync($centerIds);
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Whether this account holds a PROCTAD accreditation. True for ordinary
     * members and for staff who have also been enrolled in the registry —
     * being staff and being accredited are independent facts.
     *
     * Prefers an already-loaded relation: HandleInertiaRequests eager-loads
     * `member` on every request, so the common path must not add a query.
     */
    public function isProctadMember(): bool
    {
        return $this->relationLoaded('member')
            ? $this->member !== null
            : $this->member()->exists();
    }
}
