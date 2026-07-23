<?php

use App\Http\Controllers\AssignmentConfirmationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BlacklistController;
use App\Http\Controllers\CertificateApprovalController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicateMembersController;
use App\Http\Controllers\EmailLogController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\EvaluationMonitoringController;
use App\Http\Controllers\ExamAssignmentController;
use App\Http\Controllers\ExaminationController;
use App\Http\Controllers\ExaminationReportController;
use App\Http\Controllers\ExamRoomController;
use App\Http\Controllers\ExamTypeController;
use App\Http\Controllers\ExamVenueController;
use App\Http\Controllers\FeeScheduleController;
use App\Http\Controllers\FieldOfficeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LetterheadController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberRequirementController;
use App\Http\Controllers\MyProctadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OepAssignmentController;
use App\Http\Controllers\OtherExaminationPersonnelController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\ScannerSessionController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\ServiceHistoryController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SignatoryController;
use App\Http\Controllers\StaffingController;
use App\Http\Controllers\TrainingAssignmentController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerifyCertificateController;
use App\Http\Controllers\VerifyController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Pages
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', fn () => Inertia::render('About'))->name('about');
Route::get('/benefits', fn () => Inertia::render('Benefits'))->name('benefits');
Route::get('/qualifications', fn () => Inertia::render('Qualifications'))->name('qualifications');
Route::get('/application-process', fn () => Inertia::render('ApplicationProcess'))->name('application-process');
Route::get('/faqs', fn () => Inertia::render('Faqs'))->name('faqs');
Route::get('/contact', fn () => Inertia::render('Contact'))->name('contact');
Route::get('/privacy-policy', fn () => Inertia::render('PrivacyPolicy'))->name('privacy');
Route::get('/terms-and-conditions', fn () => Inertia::render('Terms'))->name('terms');
Route::get('/maintenance', fn () => Inertia::render('Maintenance'))->name('maintenance');
// Public QR verification. Throttled because certificate numbers are sequential
// (RO8-CAP-2026-00001, -00002, …), so an unthrottled endpoint lets anyone walk
// the range and harvest every releasee's name, PROCTAD ID and testing center.
// 10/min is far above scanning one QR code and far below a useful sweep.
Route::get('/verify/{proctadId}', VerifyController::class)
    ->middleware('throttle:10,1')
    ->name('verify');
Route::get('/verify-certificate/{certificateNo}', VerifyCertificateController::class)
    ->middleware('throttle:10,1')
    ->name('verify-certificate');

// Public QR scanner. Unauthenticated to *use*, but only reachable through a
// token an admin issued for one examination/training (and venue), which
// expires and can be revoked — see ResolveScannerSession. The token carries the
// issuing user, so attendance writes stay attributable and field-office
// scoping survives; the page itself shows identity only, no service history.
// Throttled per token (see AppServiceProvider's 'scanner-link' limiter), which
// caps a leaked link's ability to walk the sequential PROCTAD ID range without
// penalising a venue running several phones through one NAT.
Route::middleware(['scanner.session', 'throttle:scanner-link'])->group(function () {
    Route::get('/scan/{token}', ScannerController::class)->name('scan');
    Route::post('/scan/{token}/mark-attendance', [ScannerController::class, 'bulkMarkAttendance'])
        ->name('scan.mark-attendance');
    // Exam-day cover. Pinned to the session's own examination and venue in
    // ScannerController::coverableAssignment, exactly as scanning is.
    Route::post('/scan/{token}/mark-absent', [ScannerController::class, 'markAbsent'])
        ->name('scan.mark-absent');
    Route::post('/scan/{token}/activate-alternate', [ScannerController::class, 'activateAlternate'])
        ->name('scan.activate-alternate');
});

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
    Route::get('/auth/google/cancel', [GoogleAuthController::class, 'cancel'])->name('google.cancel');

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

    // Staff who are also accredited members switch between the staff console
    // and their own PROCTAD pages. Presentation only — see App\Support\Workspace.
    Route::post('/workspace', [WorkspaceController::class, 'update'])->name('workspace.update');

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
    // Submission only — `complied` stays staff-controlled (see the controller).
    Route::post('/my/requirements/{requirement}', [MyProctadController::class, 'uploadRequirement'])
        ->name('my.requirements.upload');
    Route::get('/my/assignments', [MyProctadController::class, 'assignments'])->name('my.assignments');
    // Own-record only, enforced in the controller. The emailed signed link
    // (assignments.confirm.store) remains the route for members who are not
    // signed in; both share AssignmentResponder.
    Route::post('/my/assignments/{assignment}/respond', [MyProctadController::class, 'respondToAssignment'])
        ->name('my.assignments.respond');
    Route::get('/my/certificates', [MyProctadController::class, 'certificates'])->name('my.certificates');
    Route::get('/my/trainings', [MyProctadController::class, 'trainings'])->name('my.trainings');

    // Owning members may download their own released certificates (policy-checked).
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])
        ->name('certificates.download');
    Route::get('/certificates/{certificate}/view', [CertificateController::class, 'view'])
        ->name('certificates.view');
    Route::get('/certificates/{certificate}/preview', [CertificateController::class, 'preview'])
        ->name('certificates.preview');

    // Own-record access is allowed by MemberPolicy::view.
    Route::get('/members/{member}/photo', [MemberController::class, 'photo'])->name('members.photo');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');

    Route::middleware('role:super_admin,esd_admin,fo_admin,field_director')->group(function () {
        Route::get('/scanner', ScannerController::class)->name('scanner');
        Route::post('/scanner/mark-attendance', [ScannerController::class, 'bulkMarkAttendance'])
            ->name('scanner.mark-attendance');
        Route::post('/scanner/mark-absent', [ScannerController::class, 'markAbsent'])
            ->name('scanner.mark-absent');
        Route::post('/scanner/activate-alternate', [ScannerController::class, 'activateAlternate'])
            ->name('scanner.activate-alternate');

        // Issue / revoke the public scanner links above.
        Route::post('/scanner-sessions', [ScannerSessionController::class, 'store'])
            ->name('scanner-sessions.store');
        Route::post('/scanner-sessions/{scannerSession}/revoke', [ScannerSessionController::class, 'revoke'])
            ->name('scanner-sessions.revoke');
    });

    Route::middleware('role:super_admin,director_iv,director_iii,field_director')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    // ESD Admin is included as a fallback approver alongside Super Admin, in
    // case the primary approver (Field Director/Management) is unavailable.
    Route::middleware('role:super_admin,esd_admin,director_iv,director_iii,field_director')->group(function () {
        Route::get('/approvals', [CertificateApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/certificates/{certificate}/approve', [CertificateApprovalController::class, 'approve'])
            ->name('certificates.approve');
        Route::post('/certificates/{certificate}/disapprove', [CertificateApprovalController::class, 'disapprove'])
            ->name('certificates.disapprove');
        Route::post('/certificates/bulk-approve', [CertificateApprovalController::class, 'bulkApprove'])
            ->name('certificates.bulk-approve');
        Route::post('/certificates/bulk-disapprove', [CertificateApprovalController::class, 'bulkDisapprove'])
            ->name('certificates.bulk-disapprove');
    });

    Route::middleware('role:super_admin,esd_admin,fo_admin,field_director')->group(function () {
        Route::get('/blacklists', [BlacklistController::class, 'index'])->name('blacklists.index');
        Route::get('/blacklists/members/search', [BlacklistController::class, 'searchMembers'])->name('blacklists.members.search');
        Route::post('/blacklists', [BlacklistController::class, 'store'])->name('blacklists.store');
        Route::post('/blacklists/{blacklist}/lift', [BlacklistController::class, 'lift'])->name('blacklists.lift');
    });

    Route::middleware('role:super_admin,esd_admin,director_iv,director_iii,field_director,fo_admin')->group(function () {
        Route::resource('members', MemberController::class);
        Route::get('/members/{member}/details', [MemberController::class, 'details'])
            ->name('members.details');
        Route::get('/members/{member}/edit-data', [MemberController::class, 'editData'])
            ->name('members.edit-data');
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

        Route::get('/service-history', [ServiceHistoryController::class, 'index'])->name('service-history.index');
        Route::get('/service-history/{member}', [ServiceHistoryController::class, 'show'])->name('service-history.show');

        Route::resource('signatories', SignatoryController::class)->only('index', 'store', 'update', 'destroy');
        Route::get('/signatories/{signatory}/signature', [SignatoryController::class, 'signature'])
            ->name('signatories.signature');
        Route::resource('schools', SchoolController::class)->only('index', 'store', 'update', 'destroy');

        Route::resource('other-examination-personnel', OtherExaminationPersonnelController::class);
        Route::get('/other-examination-personnel/{otherExaminationPersonnel}/details', [OtherExaminationPersonnelController::class, 'details'])
            ->name('other-examination-personnel.details');
        Route::get('/other-examination-personnel/{otherExaminationPersonnel}/edit-data', [OtherExaminationPersonnelController::class, 'editData'])
            ->name('other-examination-personnel.edit-data');
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
        Route::patch('/examinations/{examination}/toggle-visibility', [ExaminationController::class, 'toggleVisibility'])
            ->name('examinations.toggle-visibility');
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

        // Exam-day cover: record a no-show, then call an Alternate Examiner in
        // from the venue's standby pool. The venue scanner reaches the same
        // operations through ScannerController.
        Route::post('/assignments/{assignment}/absent', [ExamAssignmentController::class, 'markAbsent'])
            ->name('assignments.absent');
        Route::delete('/assignments/{assignment}/absent', [ExamAssignmentController::class, 'clearAbsence'])
            ->name('assignments.absent.clear');
        Route::post('/assignments/{assignment}/alternate', [ExamAssignmentController::class, 'activateAlternate'])
            ->name('assignments.alternate.activate');
        Route::delete('/assignments/{assignment}/alternate', [ExamAssignmentController::class, 'standDownAlternate'])
            ->name('assignments.alternate.stand-down');
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
            ->only('index', 'store', 'update', 'destroy');
        Route::get('/trainings/{training}/modal', [TrainingController::class, 'modal'])
            ->name('trainings.modal');
        Route::get('/trainings/{training}', [TrainingController::class, 'show'])
            ->name('trainings.show');
        Route::post('/trainings/{training}/complete', [TrainingController::class, 'complete'])
            ->name('trainings.complete');
        Route::post('/trainings/{training}/assignments', [TrainingAssignmentController::class, 'store'])
            ->name('trainings.assignments.store');
        Route::put('/training-assignments/{assignment}', [TrainingAssignmentController::class, 'update'])
            ->name('training-assignments.update');
        Route::delete('/training-assignments/{assignment}', [TrainingAssignmentController::class, 'destroy'])
            ->name('training-assignments.destroy');

        Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
        Route::post('/certificates/{certificate}/regenerate', [CertificateController::class, 'regenerate'])
            ->name('certificates.regenerate');
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
        // The rendered body of one sent email, fetched when an admin opens it.
        Route::get('/email-logs/{emailLog}', [EmailLogController::class, 'show'])->name('email-logs.show');

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
