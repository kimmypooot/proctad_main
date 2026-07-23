<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Keep the user ↔ testing-center pivot derived from the user's field office:
     * a user operates at every center their office handles. Running this from the
     * model's saved event covers every write path at once — admin console,
     * self-registration, Google sign-up — so the link can never drift from
     * field_office_id.
     *
     * Only acts when the office actually changed (which also covers clearing it
     * to null for regional staff) or a brand-new user arrived with an office set;
     * a new user with no office has no pivot rows to clear, so it skips the
     * needless query. Migrations write the pivot through the query builder, not
     * Eloquent, so this observer never double-fires during them.
     */
    public function saved(User $user): void
    {
        $relevant = $user->wasChanged('field_office_id')
            || ($user->wasRecentlyCreated && $user->field_office_id !== null);

        if (! $relevant) {
            return;
        }

        $user->syncTestingCentersFromFieldOffice();
    }
}
