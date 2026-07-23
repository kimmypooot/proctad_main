<?php

namespace App\Http\Middleware;

use App\Models\Certificate;
use App\Models\Setting;
use App\Support\Workspace;
use Illuminate\Http\Request;
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
            'notifications' => fn () => $request->user() ? [
                'unread_count' => $request->user()->unreadNotifications()->count(),
                'items' => $request->user()->notifications()->latest()->take(8)->get()
                    ->map(fn ($n) => [
                        'id' => $n->id,
                        'title' => $n->data['title'] ?? '',
                        'body' => $n->data['body'] ?? '',
                        'url' => $n->data['url'] ?? null,
                        'read_at' => $n->read_at,
                        'created_at' => $n->created_at->diffForHumans(),
                    ]),
            ] : null,
        ];
    }
}
