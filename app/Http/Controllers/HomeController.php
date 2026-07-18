<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\ExamAssignment;
use App\Models\Member;
use App\Models\Training;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Home', [
            'stats' => [
                ['value' => Member::count(), 'label' => 'Registered Members'],
                ['value' => Member::where('status', MemberStatus::Active)->count(), 'label' => 'Certified Test Administrators'],
                ['value' => Training::whereNotNull('completed_at')->count(), 'label' => 'Trainings Conducted'],
                ['value' => Member::distinct('agency')->count('agency'), 'label' => 'Partner Agencies'],
                ['value' => ExamAssignment::whereNotNull('attendance_confirmed_at')->count(), 'label' => 'Completed Assessments'],
            ],
        ]);
    }
}
