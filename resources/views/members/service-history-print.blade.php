<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Service History — {{ $member->proctad_id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; margin: 2cm; font-size: 13px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1e293b; padding-bottom: 12px; margin-bottom: 20px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #64748b; font-size: 12px; }
        .id { font-family: monospace; font-weight: bold; color: #1e3a8a; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        th { background: #f1f5f9; text-transform: uppercase; letter-spacing: 0.03em; font-size: 10px; color: #64748b; }
        .totals { margin-top: 16px; font-size: 12px; color: #334155; }
        .print-btn { margin-bottom: 16px; }
        @media print {
            .print-btn { display: none; }
            body { margin: 1.2cm; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print</button>

    <header>
        <div>
            <h1>{{ $member->name }}</h1>
            <p class="meta"><span class="id">{{ $member->proctad_id }}</span> &middot; {{ $member->fieldOffice?->name ?? 'Unassigned' }}</p>
        </div>
        <div class="meta">
            Service History Report<br>
            Generated {{ now()->format('M d, Y') }}
        </div>
    </header>

    <table>
        <thead>
            <tr>
                <th>Examination</th>
                <th>Date</th>
                <th>Role Performed</th>
                <th>Attendance</th>
                <th>Rating</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($serviceHistory as $assignment)
                <tr>
                    <td>{{ $assignment->examination?->title }}</td>
                    <td>{{ $assignment->examination?->exam_date?->format('M d, Y') }}</td>
                    <td>{{ $assignment->role->label() }}</td>
                    <td>{{ $assignment->attendance_confirmed_at ? 'Confirmed' : 'Not confirmed' }}</td>
                    <td>{{ $assignment->performance_rating?->label() ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No service records on file.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="totals">
        Total examinations served: {{ $serviceHistory->count() }} &middot;
        Attendance-confirmed: {{ $serviceHistory->whereNotNull('attendance_confirmed_at')->count() }}
    </p>
</body>
</html>
