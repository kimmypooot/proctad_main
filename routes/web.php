<?php

use App\Http\Controllers\AssignmentConfirmationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ExamRoomController;
use App\Http\Controllers\ExamVenueController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CertificateApprovalController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicateMembersController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\EvaluationMonitoringController;
use App\Http\Controllers\ExamAssignmentController;
use App\Http\Controllers\ExaminationController;
use App\Http\Controllers\ExaminationReportController;
use App\Http\Controllers\ExamTypeController;
use App\Http\Controllers\FeeScheduleController;
use App\Http\Controllers\FieldOfficeController;
use App\Http\Controllers\LetterheadController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberRequirementController;
use App\Http\Controllers\MyProctadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OepAssignmentController;
use App\Http\Controllers\OtherExaminationPersonnelController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SignatoryController;
use App\Http\Controllers\StaffingController;
use App\Http\Controllers\TrainingAssignmentController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerifyCertificateController;
use App\Http\Controllers\VerifyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Pages
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => Inertia::render('Home'))->name('home');
Route::get('/about', fn () => Inertia::render('About'))->name('about');
Route::get('/benefits', fn () => Inertia::render('Benefits'))->name('benefits');
Route::get('/qualifications', fn () => Inertia::render('Qualifications'))->name('qualifications');
Route::get('/application-process', fn () => Inertia::render('ApplicationProcess'))->name('application-process');
Route::get('/faqs', fn () => Inertia::render('Faqs'))->name('faqs');
Route::get('/news', fn () => Inertia::render('News'))->name('news');
Route::get('/contact', fn () => Inertia::render('Contact'))->name('contact');
Route::get('/privacy-policy', fn () => Inertia::render('PrivacyPolicy'))->name('privacy');
Route::get('/terms-and-conditions', fn () => Inertia::render('Terms'))->name('terms');
Route::get('/maintenance', fn () => Inertia::render('Maintenance'))->name('maintenance');
Route::get('/verify/{proctadId}', VerifyController::class)->name('verify');
Route::get('/verify-certificate/{certificateNo}', VerifyCertificateController::class)->name('verify-certificate');

// Assignment confirmation: opened from an emailed signed link, no login required.
Route::get('/assignments/{assignment}/confirm', [AssignmentConfirmationController::class, 'show'])
    ->middleware('signed')
    ->name('assignments.confirm');
Route::post('/assignments/{assignment}/confirm', [AssignmentConfirmationController::class, 'store'])
    ->middleware('signed')
    ->name('assignments.confirm.store');

// Post-Examination Evaluation: public form, no login — respondent searches for
// their own assignment; designation/room/hierarchy are derived server-side.
Route::get('/evaluation', [EvaluationController::class, 'create'])->name('evaluations.create');
Route::get('/evaluation/search', [EvaluationController::class, 'search'])->name('evaluations.search');
Route::get('/evaluation/assignments/{assignment}', [EvaluationController::class, 'resolve'])->name('evaluations.resolve');
Route::post('/evaluation', [EvaluationController::class, 'store'])->name('evaluations.store');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/member/login', fn () => Inertia::render('Auth/MemberLogin'))->name('member.login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Area
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/change-password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/change-password', [PasswordController::class, 'update'])->name('password.update');

    // Member self-service (also covers dual-role staff who are PROCTAD members).
    Route::get('/my/profile', [MyProctadController::class, 'profile'])->name('my.profile');
    Route::put('/my/profile', [MyProctadController::class, 'updateProfile'])->name('my.profile.update');
    Route::get('/my/qr-code', [MyProctadController::class, 'qrCode'])->name('my.qr-code');
    Route::get('/my/id-card/download', [MyProctadController::class, 'idCardDownload'])->name('my.id-card.download');
    Route::get('/my/service-history', [MyProctadController::class, 'serviceHistory'])->name('my.service-history');
    Route::get('/my/service-history/print', [MyProctadController::class, 'printServiceHistory'])
        ->name('my.service-history.print');
    Route::get('/my/service-history/export', [MyProctadController::class, 'exportServiceHistory'])
        ->name('my.service-history.export');
    Route::get('/my/certificates', [MyProctadController::class, 'certificates'])->name('my.certificates');
    Route::get('/my/trainings', [MyProctadController::class, 'trainings'])->name('my.trainings');

    // Owning members may download their own released certificates (policy-checked).
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])
        ->name('certificates.download');

    // Own-record access is allowed by MemberPolicy::view.
    Route::get('/members/{member}/photo', [MemberController::class, 'photo'])->name('members.photo');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');

    Route::middleware('role:super_admin,esd_admin,fo_admin')->group(function () {
        Route::get('/scanner', ScannerController::class)->name('scanner');
        Route::post('/scanner/mark-attendance', [ScannerController::class, 'bulkMarkAttendance'])
            ->name('scanner.mark-attendance');
    });

    Route::middleware('role:super_admin,management,field_director')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/approvals', [CertificateApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/certificates/{certificate}/approve', [CertificateApprovalController::class, 'approve'])
            ->name('certificates.approve');
        Route::post('/certificates/{certificate}/disapprove', [CertificateApprovalController::class, 'disapprove'])
            ->name('certificates.disapprove');
    });

    Route::middleware('role:super_admin,esd_admin,management,field_director,fo_admin')->group(function () {
        Route::resource('members', MemberController::class);
        Route::get('/members/{member}/id-card/download', [MemberController::class, 'downloadIdCard'])
            ->name('members.id-card.download');
        Route::post('/members/id-cards/download-bulk', [MemberController::class, 'downloadIdCardBulk'])
            ->name('members.id-cards.download-bulk');
        Route::put('/members/{member}/requirements/{requirement}', [MemberRequirementController::class, 'update'])
            ->name('members.requirements.update');
        Route::get('/members/{member}/requirements/{requirement}/download', [MemberRequirementController::class, 'download'])
            ->name('members.requirements.download');
        Route::get('/members/{member}/service-history/print', [MemberController::class, 'printServiceHistory'])
            ->name('members.service-history.print');
        Route::get('/members/{member}/service-history/export', [MemberController::class, 'exportServiceHistory'])
            ->name('members.service-history.export');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/members', [ReportController::class, 'exportMembers'])->name('reports.export.members');
        Route::get('/reports/export/service-records', [ReportController::class, 'exportServiceRecords'])
            ->name('reports.export.service-records');
        Route::get('/reports/export/training-attendance', [ReportController::class, 'exportTrainingAttendance'])
            ->name('reports.export.training-attendance');

        Route::get('/evaluation-monitoring', [EvaluationMonitoringController::class, 'index'])->name('evaluation-monitoring.index');
        Route::get('/evaluation-monitoring/{evaluation}', [EvaluationMonitoringController::class, 'show'])->name('evaluation-monitoring.show');

        Route::resource('signatories', SignatoryController::class)->only('index', 'store', 'update', 'destroy');
        Route::resource('schools', SchoolController::class)->only('index', 'store', 'update', 'destroy');

        Route::resource('other-examination-personnel', OtherExaminationPersonnelController::class);
        Route::get('/other-examination-personnel/{otherExaminationPersonnel}/photo', [OtherExaminationPersonnelController::class, 'photo'])
            ->name('other-examination-personnel.photo');
        Route::get('/other-examination-personnel/{otherExaminationPersonnel}/id-card/download', [OtherExaminationPersonnelController::class, 'downloadIdCard'])
            ->name('other-examination-personnel.id-card.download');
        Route::post('/venues/{venue}/oep-assignments', [OepAssignmentController::class, 'store'])
            ->name('venues.oep-assignments.store');
        Route::delete('/oep-assignments/{assignment}', [OepAssignmentController::class, 'destroy'])
            ->name('oep-assignments.destroy');
        Route::patch('/oep-assignments/{assignment}/attendance', [OepAssignmentController::class, 'markAttendance'])
            ->name('oep-assignments.attendance');

        Route::resource('examinations', ExaminationController::class)
            ->only('index', 'store', 'show', 'update', 'destroy');
        Route::get('/examinations/{examination}/room-assignments/export', [ExaminationController::class, 'exportRoomAssignments'])
            ->name('examinations.room-assignments.export');
        Route::post('/examinations/{examination}/venues', [ExamVenueController::class, 'store'])
            ->name('examinations.venues.store');
        Route::delete('/venues/{venue}', [ExamVenueController::class, 'destroy'])
            ->name('venues.destroy');
        Route::get('/venues/{venue}/rooms', [ExamRoomController::class, 'index'])
            ->name('venues.rooms.index');
        Route::post('/venues/{venue}/rooms', [ExamRoomController::class, 'store'])
            ->name('venues.rooms.store');
        Route::post('/venues/{venue}/rooms/generate', [ExamRoomController::class, 'bulkGenerate'])
            ->name('venues.rooms.generate');
        Route::post('/venues/{venue}/rooms/add-more', [ExamRoomController::class, 'bulkAdd'])
            ->name('venues.rooms.add-more');
        Route::delete('/venues/{venue}/rooms', [ExamRoomController::class, 'clearAll'])
            ->name('venues.rooms.clear');
        Route::post('/venues/{venue}/rooms/designations', [ExamRoomController::class, 'overrideDesignation'])
            ->name('venues.rooms.designations');
        Route::put('/exam-rooms/{room}', [ExamRoomController::class, 'update'])
            ->name('exam-rooms.update');
        Route::delete('/exam-rooms/{room}', [ExamRoomController::class, 'destroy'])
            ->name('exam-rooms.destroy');
        Route::post('/examinations/{examination}/assignments', [ExamAssignmentController::class, 'store'])
            ->name('examinations.assignments.store');
        Route::post('/examinations/{examination}/assignments/bulk', [ExamAssignmentController::class, 'bulkStore'])
            ->name('examinations.assignments.bulk-store');
        Route::post('/assignments/bulk-confirm', [ExamAssignmentController::class, 'bulkConfirm'])
            ->name('assignments.bulk-confirm');
        Route::put('/assignments/{assignment}', [ExamAssignmentController::class, 'update'])
            ->name('assignments.update');
        Route::patch('/assignments/{assignment}/room', [ExamAssignmentController::class, 'assignRoom'])
            ->name('assignments.assign-room');
        Route::delete('/assignments/{assignment}', [ExamAssignmentController::class, 'destroy'])
            ->name('assignments.destroy');
        Route::post('/assignments/{assignment}/designation-order', [ExamAssignmentController::class, 'requestDesignationOrder'])
            ->name('assignments.designation-order');
        Route::post('/assignments/{assignment}/send-confirmation', [AssignmentConfirmationController::class, 'send'])
            ->name('assignments.send-confirmation');
        Route::post('/assignments/{assignment}/force-reassign', [ExamAssignmentController::class, 'forceReassign'])
            ->name('assignments.force-reassign');
        Route::post('/venues/{venue}/staffing/randomize', [StaffingController::class, 'randomize'])
            ->name('venues.staffing.randomize');
        Route::post('/venues/{venue}/staffing/clear', [StaffingController::class, 'clear'])
            ->name('venues.staffing.clear');

        Route::get('/examinations/{examination}/reports/room-assignment', [ExaminationReportController::class, 'roomAssignment'])
            ->name('examinations.reports.room-assignment');
        Route::get('/examinations/{examination}/reports/room-assignment/precheck', [ExaminationReportController::class, 'roomAssignmentPrecheck'])
            ->name('examinations.reports.room-assignment.precheck');
        Route::get('/examinations/{examination}/reports/payroll', [ExaminationReportController::class, 'payroll'])
            ->name('examinations.reports.payroll');
        Route::get('/examinations/{examination}/reports/payroll/precheck', [ExaminationReportController::class, 'payrollPrecheck'])
            ->name('examinations.reports.payroll.precheck');
        Route::get('/examinations/{examination}/reports/payroll-posting', [ExaminationReportController::class, 'payrollPosting'])
            ->name('examinations.reports.payroll-posting');
        Route::get('/examinations/{examination}/reports/payroll-posting/precheck', [ExaminationReportController::class, 'payrollPostingPrecheck'])
            ->name('examinations.reports.payroll-posting.precheck');

        Route::resource('trainings', TrainingController::class)
            ->only('index', 'store', 'show', 'update', 'destroy');
        Route::post('/trainings/{training}/complete', [TrainingController::class, 'complete'])
            ->name('trainings.complete');
        Route::post('/trainings/{training}/assignments', [TrainingAssignmentController::class, 'store'])
            ->name('trainings.assignments.store');
        Route::put('/training-assignments/{assignment}', [TrainingAssignmentController::class, 'update'])
            ->name('training-assignments.update');
        Route::delete('/training-assignments/{assignment}', [TrainingAssignmentController::class, 'destroy'])
            ->name('training-assignments.destroy');

        Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
        Route::get('/exam-types', [ExamTypeController::class, 'index'])->name('exam-types.index');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::post('/assignments/bulk-revoke', [ExamAssignmentController::class, 'bulkRevoke'])
            ->name('assignments.bulk-revoke');
        Route::post('/certificates/bulk-resign', [CertificateController::class, 'bulkResign'])
            ->name('certificates.bulk-resign');
        Route::get('/reports/duplicate-members', [DuplicateMembersController::class, 'index'])
            ->name('reports.duplicate-members');
    });

    Route::middleware('role:super_admin,esd_admin')->group(function () {
        Route::get('/letterheads', [LetterheadController::class, 'index'])->name('letterheads.index');
        Route::post('/letterheads', [LetterheadController::class, 'store'])->name('letterheads.store');
        Route::post('/letterheads/{letterhead}/activate', [LetterheadController::class, 'activate'])->name('letterheads.activate');
        Route::delete('/letterheads/{letterhead}', [LetterheadController::class, 'destroy'])->name('letterheads.destroy');
        Route::get('/letterheads/{letterhead}/preview', [LetterheadController::class, 'preview'])->name('letterheads.preview');

        Route::post('/exam-types', [ExamTypeController::class, 'store'])->name('exam-types.store');
        Route::put('/exam-types/{examType}', [ExamTypeController::class, 'update'])->name('exam-types.update');
        Route::delete('/exam-types/{examType}', [ExamTypeController::class, 'destroy'])->name('exam-types.destroy');

        Route::get('/field-offices', [FieldOfficeController::class, 'index'])->name('field-offices.index');
        Route::post('/field-offices', [FieldOfficeController::class, 'store'])->name('field-offices.store');
        Route::put('/field-offices/{fieldOffice}', [FieldOfficeController::class, 'update'])->name('field-offices.update');

        Route::get('/fee-schedules', [FeeScheduleController::class, 'index'])->name('fee-schedules.index');
        Route::put('/fee-schedules', [FeeScheduleController::class, 'update'])->name('fee-schedules.update');

        Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::put('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'store'])->name('settings.store');
        Route::put('/settings/{setting}', [SettingController::class, 'update'])->name('settings.update');
        Route::delete('/settings/{setting}', [SettingController::class, 'destroy'])->name('settings.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/send-password-reset', [UserController::class, 'sendPasswordReset'])
            ->name('users.send-password-reset');
    });
});

/*
|--------------------------------------------------------------------------
| Fallback — 404
|--------------------------------------------------------------------------
*/

Route::fallback(fn () => Inertia::render('NotFound')->toResponse(request())->setStatusCode(404));
