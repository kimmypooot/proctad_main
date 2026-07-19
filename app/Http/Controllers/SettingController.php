<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * Plain-language presentation for the settings staff actually touch. The
     * table underneath is a raw key/value store, which is fine for storage but
     * unusable for a Testing Center administrator who shouldn't have to know
     * that `1` means "on" — so each known key gets a label, an explanation of
     * what changes in the real world, and a control suited to its type.
     *
     * Keys absent from here still render, labelled from the key itself. Nothing
     * breaks when someone adds a custom setting; it just looks plainer.
     */
    private const CATALOGUE = [
        Setting::EMAIL_SENDING_ENABLED => [
            'label' => 'Send emails',
            'help' => 'When off, the system stops sending every email — confirmation requests, reminders, released certificates, and password resets. Nothing is lost: messages are still recorded so you can see what would have gone out.',
            'group' => 'Email',
            'control' => 'toggle',
            'on_label' => 'Emails are being sent',
            'off_label' => 'Emails are paused',
        ],
        'assignment_confirmation_expiry_days' => [
            'label' => 'Days a confirmation link stays valid',
            'help' => 'How long a test administrator has to accept or decline before the emailed link stops working and has to be sent again.',
            'group' => 'Examinations',
            'control' => 'number',
            'suffix' => 'days',
        ],
        'assignment_reminder_after_days' => [
            'label' => 'Send a reminder after',
            'help' => 'If a test administrator has not responded to their assignment, a reminder email goes out this many days later.',
            'group' => 'Examinations',
            'control' => 'number',
            'suffix' => 'days',
        ],
        'default_member_status' => [
            'label' => 'Status for newly added members',
            'help' => 'The accreditation status a PROCTAD member starts with when their record is first created.',
            'group' => 'Members',
            'control' => 'select',
            'options' => [
                ['value' => 'active', 'label' => 'Active — may be assigned to examinations'],
                ['value' => 'inactive', 'label' => 'Inactive — kept on file but not assignable'],
            ],
        ],
        'proctad_id_prefix' => [
            'label' => 'PROCTAD ID prefix',
            'help' => 'The text at the front of every generated PROCTAD ID. Changing this only affects IDs issued from now on — existing member IDs are never rewritten.',
            'group' => 'Members',
            'control' => 'text',
        ],
        'site_maintenance_mode' => [
            'label' => 'Maintenance mode',
            'help' => 'When on, the public website and the member portal show a maintenance notice. '
                .'Commission staff are not affected and keep working normally. '
                .'PROCTAD members can still sign in, but will see the notice once they do. '
                .'These stay open to everyone even while it is on: sign-in, emailed assignment '
                .'confirmation links, QR verification of IDs and certificates, and the '
                .'post-examination evaluation form.',
            'group' => 'Website',
            'control' => 'toggle',
            'on_label' => 'Public website is closed',
            'off_label' => 'Public website is open',
        ],
    ];

    /** Order groups deliberately rather than alphabetically — most-used first. */
    private const GROUP_ORDER = ['Email', 'Examinations', 'Members', 'Website', 'Other'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Setting::class);

        return Inertia::render('Settings/General/Index', [
            'settings' => Setting::orderBy('key')->get()
                ->map(function (Setting $setting) {
                    $meta = self::CATALOGUE[$setting->key] ?? [];

                    return [
                        ...$setting->only('id', 'key', 'value', 'type', 'description', 'is_public'),
                        'label' => $meta['label'] ?? $this->humanise($setting->key),
                        // Prefer the curated explanation; fall back to whatever
                        // description was typed in when the setting was added.
                        'help' => $meta['help'] ?? $setting->description,
                        'group' => $meta['group'] ?? 'Other',
                        'control' => $meta['control'] ?? $this->controlFor($setting),
                        'options' => $meta['options'] ?? null,
                        'suffix' => $meta['suffix'] ?? null,
                        'on_label' => $meta['on_label'] ?? 'On',
                        'off_label' => $meta['off_label'] ?? 'Off',
                        'is_known' => $meta !== [],
                    ];
                }),
            'groupOrder' => self::GROUP_ORDER,
            'can' => ['manage' => $request->user()->can('manage', Setting::class)],
        ]);
    }

    /** `proctad_id_prefix` → `Proctad ID prefix`, for keys not in the catalogue. */
    private function humanise(string $key): string
    {
        $words = preg_replace('/\bid\b/i', 'ID', str_replace(['_', '.'], ' ', $key));

        return ucfirst(trim($words));
    }

    private function controlFor(Setting $setting): string
    {
        return match ($setting->type) {
            'boolean' => 'toggle',
            'number' => 'number',
            default => 'text',
        };
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_.]+$/', 'unique:settings,key'],
            'value' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['string', 'number', 'boolean', 'json'])],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['required', 'boolean'],
        ]);

        Setting::create([
            'key' => $validated['key'],
            'value' => $validated['value'] ?? '',
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Setting added.');
    }

    public function update(Request $request, Setting $setting): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $validated = $request->validate([
            'value' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['required', 'boolean'],
        ]);

        $setting->update([
            'value' => $validated['value'] ?? '',
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', "\"{$setting->key}\" updated.");
    }

    public function destroy(Setting $setting): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $setting->delete();

        return back()->with('success', "\"{$setting->key}\" removed.");
    }
}
