<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which "hat" a signed-in user is currently wearing.
 *
 * Commission staff can also be accredited PROCTAD members — a Field Office
 * Staff who proctors, a Director who chairs the REC. They are one person with
 * one account (see MemberController::resolveAccount, which links a new member
 * record to an existing account by email rather than minting a second login),
 * so the two hats are a view over a single identity, not two identities.
 *
 * IMPORTANT: this is presentation only — which dashboard and which navigation.
 * Authorization must always read the real `users.role`, never the workspace.
 * The value lives in the session, which is user-influenced through the switch
 * endpoint; treating it as a permission would turn a UI convenience into a
 * privilege boundary. Member self-service is safe either way because every
 * /my/* action scopes by user_id (see MyProctadController::ownMember).
 */
class Workspace
{
    public const STAFF = 'staff';

    public const MEMBER = 'member';

    public const SESSION_KEY = 'workspace';

    /**
     * The workspace in effect for this request.
     */
    public static function current(Request $request): string
    {
        $user = $request->user();

        if ($user === null) {
            return self::STAFF;
        }

        // A plain member has no staff console to switch back to, so they are
        // always in the member workspace regardless of what the session says.
        if (! $user->role?->isStaff()) {
            return self::MEMBER;
        }

        if ($request->session()->get(self::SESSION_KEY) !== self::MEMBER) {
            return self::STAFF;
        }

        // The session outlives the member record: an accreditation unlinked or
        // removed while the user was switched in would otherwise leave them
        // stuck on a member dashboard with nothing behind it.
        return $user->isProctadMember() ? self::MEMBER : self::STAFF;
    }

    /**
     * Whether to offer the switcher at all — staff who hold an accreditation.
     */
    public static function availableTo(?\App\Models\User $user): bool
    {
        return $user !== null
            && (bool) $user->role?->isStaff()
            && $user->isProctadMember();
    }
}
