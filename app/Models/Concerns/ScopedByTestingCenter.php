<?php

namespace App\Models\Concerns;

use App\Models\TestingCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For records about a member — assignments, blacklists, certificates, training
 * assignments. They copy the member's testing center when written, and field
 * office staff see them by that center, the same basis the members themselves
 * are scoped by.
 *
 * They used to copy the member's field office, which stopped working when
 * external test administrators lost theirs: an office describes who a CSC
 * employee works for, and a test administrator works for their own agency.
 */
trait ScopedByTestingCenter
{
    public function testingCenter(): BelongsTo
    {
        return $this->belongsTo(TestingCenter::class);
    }

    /**
     * The office whose signatory signs for this record. Its own office when the
     * record belongs to a CSC employee, otherwise the office administering its
     * testing center.
     */
    public function administeringFieldOfficeId(): ?int
    {
        return $this->field_office_id
            ?? $this->testingCenter?->administeringFieldOfficeId();
    }

    /**
     * Records serving a testing center this user's office covers.
     *
     * A null center means the record belongs to a member who is CSC staff, and
     * so is anchored by office rather than city — deliberately not visible to
     * field office staff through this scope. Region-wide roles bypass it.
     */
    public function scopeInJurisdictionOf(Builder $query, User $user): Builder
    {
        return $query->whereIn('testing_center_id', $user->scopedTestingCenterIds());
    }
}
