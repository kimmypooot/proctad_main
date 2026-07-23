<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    private const MODEL_LABELS = [
        \App\Models\Member::class => 'Member',
        \App\Models\MemberRequirement::class => 'Eligibility Requirement',
        \App\Models\Signatory::class => 'Signatory',
        \App\Models\User::class => 'User Account',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $logs = AuditLog::query()
            ->with('user:id,name')
            // Field-office-scoped staff (Field Director and FO Admin) only see
            // activity within their own Field Office (spec 4.2).
            ->when($user->role->isFieldOfficeScoped(),
                fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('type'), fn ($q) => $q->where('auditable_type', $request->string('type')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->user?->name ?? 'System',
                'model' => self::MODEL_LABELS[$log->auditable_type] ?? class_basename($log->auditable_type),
                'record_id' => $log->auditable_id,
                'changes' => $log->changes,
                'created_at' => $log->created_at->format('M d, Y H:i'),
            ]);

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only('action', 'type'),
            'types' => collect(self::MODEL_LABELS)
                ->map(fn ($label, $class) => ['value' => $class, 'label' => $label])
                ->values(),
        ]);
    }
}
