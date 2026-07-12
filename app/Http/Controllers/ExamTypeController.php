<?php

namespace App\Http\Controllers;

use App\Models\ExamType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExamTypeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ExamType::class);

        return Inertia::render('Settings/ExamTypes/Index', [
            'examTypes' => ExamType::withCount('examinations')->orderBy('name')->get()
                ->map(fn (ExamType $type) => [
                    ...$type->only('id', 'name', 'is_active'),
                    'examinations_count' => $type->examinations_count,
                ]),
            'can' => ['manage' => $request->user()->can('manage', ExamType::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', ExamType::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:exam_types,name'],
            'is_active' => ['required', 'boolean'],
        ]);

        ExamType::create($validated);

        return back()->with('success', 'Examination type added.');
    }

    public function update(Request $request, ExamType $examType): RedirectResponse
    {
        Gate::authorize('manage', ExamType::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:exam_types,name,'.$examType->id],
            'is_active' => ['required', 'boolean'],
        ]);

        $examType->update($validated);

        return back()->with('success', 'Examination type updated.');
    }

    public function destroy(ExamType $examType): RedirectResponse
    {
        Gate::authorize('manage', ExamType::class);

        abort_if($examType->examinations()->exists(), 422, 'Cannot remove a type that is in use by existing examinations.');

        $examType->delete();

        return back()->with('success', 'Examination type removed.');
    }
}
