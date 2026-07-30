<?php

namespace App\Http\Middleware;

use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\Setting;
use App\Support\NotificationPresenter;
use App\Support\RoleLabelRegistry;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user()?->load('member:id,user_id,photo_path');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only('id', 'name', 'first_name', 'last_name', 'email', 'role', 'field_office_id'),
                    'avatar_url' => $user->google_avatar
                        ?? ($user->member?->photo_path ? route('members.photo', $user->member) : null),
                ] : null,
            ],
            // Role display names, resolved server-side so a rename made at
            // Administration → Roles reaches the sidebar and any other client
            // code that shows a role, rather than only the pages that happen to
            // receive a label in their own props.
            'roleLabels' => fn () => $user ? RoleLabelRegistry::all() : [],
            // Which hat the user is wearing, and whether they have a second one
            // to switch to. Drives navigation and the dashboard only — never
            // access. See App\Support\Workspace.
            'workspace' => Workspace::current($request),
            'canSwitchWorkspace' => Workspace::availableTo($user),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
            // Staff see the site as normal while maintenance is on, so without a
            // banner it's easy to leave the public website closed for days.
            'maintenanceMode' => fn () => $user
                ? (bool) Setting::get('site_maintenance_mode', false)
                : false,
            // Sidebar badge. A closure, so partial reloads that don't ask for it
            // skip the COUNT entirely, and roles with no approval rights resolve
            // to 0 without querying (scopePendingApprovalFor short-circuits).
            // Named *Count, not 'pendingApprovals': DashboardController already
            // ships that key as {items, total}, and a page prop shadows a shared
            // one — reusing the name would blank the badge on /dashboard only.
            'pendingApprovalCount' => fn () => $user
                ? Certificate::query()->pendingApprovalFor($user)->count()
                : 0,
            // The member-side counterpart of pendingApprovalCount: deployments
            // the signed-in member still owes an answer on. Drives the sidebar
            // badge and the sign-in prompt, so both are computed once, here.
            'pendingAssignments' => fn () => $user?->member
                ? $this->pendingAssignments($user->member->id)
                : null,
            // The bell's peek at the latest few. The full history lives at
            // /notifications; both render through the same presenter so an item
            // cannot look like one thing in the dropdown and another on the page.
            'notifications' => fn () => $request->user() ? [
                'unread_count' => $request->user()->unreadNotifications()->count(),
                'total_count' => $request->user()->notifications()->count(),
                'items' => $request->user()->notifications()->latest()->take(8)->get()
                    ->map(fn (DatabaseNotification $notification) => NotificationPresenter::present($notification)),
            ] : null,
        ];
    }

    /**
     * What the member still has to confirm, and enough of the soonest one to
     * name it in the prompt.
     *
     * `signature` is the id list: the prompt is dismissed per set of
     * assignments, not per session, so answering one or being deployed to
     * another brings it back while an idle reload does not.
     *
     * @return array<string, mixed>
     */
    private function pendingAssignments(int $memberId): array
    {
        $assignments = ExamAssignment::query()
            ->awaitingResponseFrom($memberId)
            ->with('examination:id,title,exam_date')
            ->get()
            ->sortBy(fn (ExamAssignment $assignment) => $assignment->examination?->exam_date)
            ->values();

        $soonest = $assignments->first();

        return [
            'count' => $assignments->count(),
            'signature' => $assignments->pluck('id')->sort()->implode('-'),
            'soonest' => $soonest ? [
                'exam_title' => $soonest->examination?->title,
                'exam_date' => $soonest->examination?->exam_date?->format('F j, Y (l)'),
                'role_label' => $soonest->role->label(),
            ] : null,
        ];
    }
}
