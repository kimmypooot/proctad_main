<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only report, ported from legacy's duplicate-members-report.php:
 * flags members that collide on email (belt-and-suspenders — the schema's
 * unique constraint should already prevent this) or on normalized
 * (case/whitespace-insensitive) first+last name. No merge/delete action —
 * staff investigate and resolve each group manually.
 */
class DuplicateMembersController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reports/DuplicateMembers', [
            'emailGroups' => $this->emailGroups(),
            'nameGroups' => $this->nameGroups(),
        ]);
    }

    private function emailGroups(): array
    {
        $duplicateEmails = Member::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        return $duplicateEmails->map(fn (string $email) => [
            'key' => $email,
            'label' => $email,
            'members' => $this->present(Member::where('email', $email)),
        ])->values()->all();
    }

    private function nameGroups(): array
    {
        $duplicateNames = Member::query()
            ->selectRaw('UPPER(TRIM(first_name)) as norm_first, UPPER(TRIM(last_name)) as norm_last')
            ->groupBy('norm_first', 'norm_last')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $duplicateNames->map(fn ($row) => [
            'key' => "{$row->norm_first}|{$row->norm_last}",
            'label' => ucwords(mb_strtolower("{$row->norm_first} {$row->norm_last}")),
            'members' => $this->present(
                Member::whereRaw('UPPER(TRIM(first_name)) = ?', [$row->norm_first])
                    ->whereRaw('UPPER(TRIM(last_name)) = ?', [$row->norm_last])
            ),
        ])->values()->all();
    }

    private function present($query): array
    {
        return $query->with('fieldOffice:id,name')->get()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'proctad_id' => $member->proctad_id,
                'name' => $member->name,
                'email' => $member->email,
                'field_office' => $member->fieldOffice?->name ?? 'Unassigned',
                'status_label' => $member->status->label(),
                'created_at' => $member->created_at->format('M d, Y'),
            ])->all();
    }
}
