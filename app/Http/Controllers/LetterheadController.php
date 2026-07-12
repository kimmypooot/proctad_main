<?php

namespace App\Http\Controllers;

use App\Models\Letterhead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LetterheadController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Letterhead::class);

        return Inertia::render('Settings/Letterheads/Index', [
            'letterheads' => Letterhead::latest()->get()->map(fn (Letterhead $letterhead) => [
                ...$letterhead->only('id', 'name', 'is_active'),
                'preview_url' => route('letterheads.preview', $letterhead),
                'is_pdf' => $letterhead->isPdf(),
                'uploaded_at' => $letterhead->created_at->format('M d, Y'),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Letterhead::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'activate' => ['boolean'],
        ]);

        $letterhead = Letterhead::create([
            'name' => $validated['name'],
            'file_path' => $request->file('file')->store('letterheads', 'local'),
            'uploaded_by' => $request->user()->id,
        ]);

        if ($request->boolean('activate')) {
            $letterhead->activate();
        }

        return back()->with('success', 'Letterhead uploaded.');
    }

    public function activate(Letterhead $letterhead): RedirectResponse
    {
        Gate::authorize('update', Letterhead::class);

        $letterhead->activate();

        return back()->with('success', "\"{$letterhead->name}\" is now the active letterhead for new certificates.");
    }

    public function destroy(Letterhead $letterhead): RedirectResponse
    {
        Gate::authorize('delete', Letterhead::class);

        Storage::disk('local')->delete($letterhead->file_path);
        $letterhead->delete();

        return back()->with('success', 'Letterhead removed.');
    }

    public function preview(Letterhead $letterhead)
    {
        Gate::authorize('viewAny', Letterhead::class);

        abort_unless(Storage::disk('local')->exists($letterhead->file_path), 404);

        return Storage::disk('local')->response($letterhead->file_path);
    }
}
