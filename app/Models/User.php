<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use App\Observers\UserObserver;
use App\Support\PermissionRegistry;
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

    /**
     * The testing centers this user's jurisdiction covers. Field-office-scoped
     * staff (FO Admin, Field Director) see and manage members by center rather
     * than by office, which is what lets Leyte I and Leyte II staff share the
     * Tacloban City roster they jointly serve.
     *
     * Read live from field_office_testing_center rather than the user's own
     * testing_center_user pivot. The pivot is derived from the same table (see
     * syncTestingCentersFromFieldOffice) but only re-syncs when the user is
     * saved, so it goes stale the moment an office gains a center — and
     * authorization must never depend on when a resync last ran.
     *
     * Deliberately not memoized on the instance. A User can outlive the state
     * it was read from — a queued job, a console command, or a request that
     * links a new center and then authorizes against it — and a cached
     * jurisdiction that has gone stale silently grants or denies the wrong
     * access. The lookup is a primary-key scan of a table with one row per
     * office-center pair, so the cost is not worth the risk.
     *
     * @return list<int>
     */
    public function scopedTestingCenterIds(): array
    {
        if ($this->field_office_id === null) {
            return [];
        }

        return DB::table('field_office_testing_center')
            ->where('field_office_id', $this->field_office_id)
            ->pluck('testing_center_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Every field office sharing any of this user's testing centers, including
     * their own. Records denormalize `field_office_id` at the time they are
     * written (certificates, blacklists, audit logs), so widening the comparison
     * to the sibling offices is what makes those records visible across a shared
     * jurisdiction without stamping a center onto all twelve tables.
     *
     * @return list<int>
     */
    public function scopedFieldOfficeIds(): array
    {
        $centerIds = $this->scopedTestingCenterIds();

        $ids = $centerIds === []
            ? []
            : DB::table('field_office_testing_center')
                ->whereIn('testing_center_id', $centerIds)
                ->distinct()
                ->pluck('field_office_id')
                ->all();

        // An office with no centers linked yet still scopes to itself, so a
        // half-configured office sees its own records rather than everything.
        if ($this->field_office_id !== null && ! in_array($this->field_office_id, $ids, true)) {
            $ids[] = $this->field_office_id;
        }

        return array_values(array_map('intval', $ids));
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
     * Whether this user's role holds the given capability. This is only half of
     * an authorization decision — the policies pair it with the scope and state
     * rules that a permission is not allowed to override. See App\Enums\Permission.
     */
    public function hasPermission(Permission $permission): bool
    {
        return PermissionRegistry::has($this->role, $permission);
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
