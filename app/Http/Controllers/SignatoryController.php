<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\FieldOffice;
use App\Models\Signatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SignatoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Signatory::class);

        $user = $request->user();

        return Inertia::render('Signatories/Index', [
            'signatories' => Signatory::with('fieldOffice:id,name,code')
                ->orderByRaw('field_office_id is not null')
                ->orderBy('field_office_id')
                ->orderByDesc('active')
                ->get()
                ->map(fn (Signatory $signatory) => [
                    ...$signatory->only('id', 'name', 'position', 'active', 'field_office_id'),
                    'field_office' => $signatory->fieldOffice?->only('id', 'name', 'code'),
                    'can_manage' => $user->can('update', $signatory),
                    'signature_url' => $signatory->signature_path
                        ? route('signatories.signature', $signatory)
                        : null,
                ]),
            'fieldOffices' => $this->assignableScopes($user),
            'can' => ['create' => $user->can('create', Signatory::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Signatory::class);

        $attributes = $this->validated($request);

        if ($request->hasFile('signature')) {
            $attributes['signature_path'] = $request->file('signature')->store('signatures', 'local');
        }

        Signatory::create($attributes);

        return back()->with('success', 'Signatory added.');
    }

    public function update(Request $request, Signatory $signatory): RedirectResponse
    {
        Gate::authorize('update', $signatory);

        $attributes = $this->validated($request);

        if ($request->hasFile('signature')) {
            $this->deleteSignatureFile($signatory);
            $attributes['signature_path'] = $request->file('signature')->store('signatures', 'local');
        } elseif ($request->boolean('remove_signature')) {
            $this->deleteSignatureFile($signatory);
            $attributes['signature_path'] = null;
        }

        $signatory->update($attributes);

        return back()->with('success', 'Signatory updated. Previously issued IDs and certificates are not affected.');
    }

    public function destroy(Signatory $signatory): RedirectResponse
    {
        Gate::authorize('delete', $signatory);

        $this->deleteSignatureFile($signatory);
        $signatory->delete();

        return back()->with('success', 'Signatory removed.');
    }

    /**
     * Serve the signature image. Gated rather than public: a specimen signature
     * is forgeable material, so it must not be fetchable by URL guess.
     */
    public function signature(Signatory $signatory)
    {
        Gate::authorize('viewAny', Signatory::class);

        abort_unless(
            $signatory->signature_path && Storage::disk('local')->exists($signatory->signature_path),
            404,
        );

        return Storage::disk('local')->response($signatory->signature_path);
    }

    /**
     * Certificates snapshot the image path at release, so the file has to stay
     * put for any certificate already referencing it — only ever delete one
     * nothing has been issued against.
     */
    private function deleteSignatureFile(Signatory $signatory): void
    {
        if (! $signatory->signature_path) {
            return;
        }

        $stillReferenced = Certificate::where('signatory_signature_path', $signatory->signature_path)->exists();

        if (! $stillReferenced) {
            Storage::disk('local')->delete($signatory->signature_path);
        }
    }

    private function validated(Request $request): array
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            // PNG only: the image overlays the certificate's printed name, so it
            // needs a transparent background — a JPEG would stamp a white box
            // over the text beneath it.
            'signature' => ['nullable', 'image', 'mimes:png', 'max:2048', 'dimensions:max_width=2000,max_height=1000'],
            'field_office_id' => [
                'nullable',
                'exists:field_offices,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                    if ($user->role->isFieldOfficeScoped() && (int) $value !== $user->field_office_id) {
                        $fail('You can only manage signatories of your own Testing Center.');
                    }
                },
            ],
        ]);

        // The upload is handled separately (stored, then recorded as
        // signature_path) — it must not reach the model as an attribute.
        unset($validated['signature']);

        return $validated;
    }

    /**
     * Scopes the current user may assign: FO admins only their own office;
     * region-wide roles get every office plus the region-wide option (null).
     */
    private function assignableScopes($user): array
    {
        if ($user->role->isFieldOfficeScoped()) {
            return FieldOffice::whereKey($user->field_office_id)->get(['id', 'name', 'code'])->all();
        }

        // Include hidden field offices already referenced by an existing signatory
        // so its edit form doesn't silently blank out that selection.
        $referencedIds = Signatory::whereNotNull('field_office_id')->distinct()->pluck('field_office_id');

        return FieldOffice::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $referencedIds))
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->all();
    }
}
