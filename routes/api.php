<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\School\StudentController;
use App\Http\Controllers\Api\School\TeacherController;
use App\Http\Controllers\Api\School\InternshipAssignmentController;
use App\Http\Controllers\Api\School\CompanyController;
use App\Http\Controllers\Api\School\DashboardController as SchoolDashboardController;
use App\Http\Controllers\Api\Student\DailyReportController;
use App\Http\Controllers\Api\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Api\Student\InternshipApplicationController;
use App\Http\Controllers\Api\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Api\Company\InternshipPositionController;
use App\Http\Controllers\Api\Company\ApplicationController as CompanyApplicationController;
use App\Http\Controllers\Api\Company\CompanySupervisorController;
use App\Http\Controllers\Api\Teacher\ApplicationApprovalController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']); // For initial setup only
    Route::post('/register-school', [AuthController::class, 'registerSchool']);
    Route::post('/register-school-admin', [AuthController::class, 'registerSchoolAdmin']);
    Route::post('/register-company', [AuthController::class, 'registerCompany']);
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    // User profile (accessible by all roles)
    Route::prefix('user')->group(function () {
        Route::get('/profile', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });


    // ========================================
    // SCHOOL ADMIN & TEACHER ROUTES
    // ========================================
    Route::middleware(['role:school_admin,teacher', 'ensure.school.scope'])->prefix('school')->group(function () {

        // Profile (Admin only)
        Route::put('profile', [AuthController::class, 'updateSchoolProfile']);

        // Dashboard
        Route::get('dashboard', [SchoolDashboardController::class, 'index']);

        // Students Management
        Route::apiResource('students', StudentController::class);

        // Teachers Management
        Route::apiResource('teachers', TeacherController::class);

        // Companies Management
        Route::get('companies/{id}/supervisors', [CompanyController::class, 'supervisors']);
        Route::apiResource('companies', CompanyController::class);

        // Internship Assignments
        Route::get('assignments/statistics', [InternshipAssignmentController::class, 'statistics']);
        Route::apiResource('assignments', InternshipAssignmentController::class);

        // Daily Reports (for viewing/approving by teacher)
        Route::get('daily-reports', [DailyReportController::class, 'indexForSchool']);
        Route::patch('daily-reports/{id}/approve', [DailyReportController::class, 'approve']);
        Route::patch('daily-reports/{id}/reject', [DailyReportController::class, 'reject']);

        // Internship Applications (for viewing by school)
        Route::get('applications', [InternshipApplicationController::class, 'indexForSchool']);
        Route::get('applications/{id}', [InternshipApplicationController::class, 'showForSchool']);

        Route::patch('applications/{id}/approve', [\App\Http\Controllers\Api\Teacher\ApplicationApprovalController::class, 'approve']);
        Route::patch('applications/{id}/reject', [\App\Http\Controllers\Api\Teacher\ApplicationApprovalController::class, 'reject']);

        Route::post('bulk-approve', [ApplicationApprovalController::class, 'bulkApprove']);

        // Admin creates assignment with teacher after company approval
        Route::post('assignments/from-application', [InternshipAssignmentController::class, 'createFromApplication']);
        Route::patch('assignments/{id}/assign-teacher', [InternshipAssignmentController::class, 'assignTeacher']);
        Route::patch('assignments/{id}/assign-mentor', [InternshipAssignmentController::class, 'assignMentor']);
    });

    // ========================================
    // STUDENT ROUTES
    // ========================================
    Route::middleware('role:student')->prefix('student')->group(function () {

        // Dashboard
        Route::get('dashboard', [StudentDashboardController::class, 'index']);

        // My Assignment
        Route::get('my-assignment', [StudentAssignmentController::class, 'myAssignment']);

        // Attendance
        Route::get('attendance/today', [\App\Http\Controllers\Api\Student\StudentAttendanceController::class, 'today']);
        Route::get('attendance', [\App\Http\Controllers\Api\Student\StudentAttendanceController::class, 'index']);
        Route::post('attendance/clock-in', [\App\Http\Controllers\Api\Student\StudentAttendanceController::class, 'clockIn']);
        Route::patch('attendance/clock-out', [\App\Http\Controllers\Api\Student\StudentAttendanceController::class, 'clockOut']);

        // Permissions (Izin)
        Route::get('permissions', [\App\Http\Controllers\Api\Student\StudentPermissionController::class, 'index']);
        Route::post('permissions', [\App\Http\Controllers\Api\Student\StudentPermissionController::class, 'store']);

        // Tasks
        Route::get('tasks', [\App\Http\Controllers\Api\Student\TaskSubmissionController::class, 'index']);
        Route::get('tasks/statistics', [\App\Http\Controllers\Api\Student\TaskSubmissionController::class, 'statistics']);
        Route::get('tasks/{id}', [\App\Http\Controllers\Api\Student\TaskSubmissionController::class, 'show']);
        Route::post('tasks/{id}/submit', [\App\Http\Controllers\Api\Student\TaskSubmissionController::class, 'submit']);
        Route::patch('tasks/{id}/in-progress', [\App\Http\Controllers\Api\Student\TaskSubmissionController::class, 'markInProgress']);

        // Daily Reports
        Route::apiResource('daily-reports', DailyReportController::class)->except(['update']);
        Route::post('daily-reports/{id}', [DailyReportController::class, 'update']); // For file upload

        // Internship Applications
        Route::get('applications', [InternshipApplicationController::class, 'index']);
        Route::post('applications', [InternshipApplicationController::class, 'store']);
        Route::delete('applications/{id}', [InternshipApplicationController::class, 'destroy']);

        // Browse Companies & Positions
        Route::get('companies', [CompanyController::class, 'browse']);
        Route::get('companies/{id}', [CompanyController::class, 'show']);
        Route::get('positions', [InternshipPositionController::class, 'browse']);
        Route::get('positions/{id}', [InternshipPositionController::class, 'show']);
    });

    // ========================================
    // COMPANY ROUTES
    // ========================================
    Route::middleware('role:company,hrd')->prefix('company')->group(function () {

        // Company Profile
        Route::get('profile', [CompanyApplicationController::class, 'profile']);
        Route::put('profile', [CompanyApplicationController::class, 'updateProfile']);

        // Internship Positions
        Route::apiResource('positions', InternshipPositionController::class);

        // Applications Management
        Route::get('applications', [CompanyApplicationController::class, 'index']);
        Route::get('applications/{id}', [CompanyApplicationController::class, 'show']);
        Route::patch('applications/{id}/approve', [CompanyApplicationController::class, 'approve']);
        Route::patch('applications/{id}/reject', [CompanyApplicationController::class, 'reject']);

        // Current Interns
        Route::get('interns', [CompanyApplicationController::class, 'currentInterns']);

        // Mentors / Supervisors
        Route::apiResource('supervisors', CompanySupervisorController::class);
    });
    // ========================================
    // TEACHER (PEMBIMBING SEKOLAH) ROUTES
    // ========================================
    Route::middleware(['role:teacher', 'ensure.school.scope'])->prefix('teacher')->group(function () {
        // Dashboard
        Route::get('dashboard', [\App\Http\Controllers\Api\Teacher\SupervisionController::class, 'dashboard']);

        // Supervised assignments (Siswa Bimbingan)
        Route::get('supervision/assignments', [\App\Http\Controllers\Api\Teacher\SupervisionController::class, 'myAssignments']);
        Route::get('supervision/by-company', [\App\Http\Controllers\Api\Teacher\SupervisionController::class, 'groupedByCompany']);
        Route::get('supervision/company/{companyId}/students', [\App\Http\Controllers\Api\Teacher\SupervisionController::class, 'companyStudents']);
        Route::post('supervision/bulk-approve-reports', [\App\Http\Controllers\Api\Teacher\SupervisionController::class, 'bulkApproveDailyReports']);

        // Tasks
        Route::get('tasks', [\App\Http\Controllers\Api\Teacher\TaskController::class, 'index']);
        Route::post('tasks', [\App\Http\Controllers\Api\Teacher\TaskController::class, 'store']);
        Route::get('tasks/{id}', [\App\Http\Controllers\Api\Teacher\TaskController::class, 'show']);
        Route::put('tasks/{id}', [\App\Http\Controllers\Api\Teacher\TaskController::class, 'update']);
        Route::delete('tasks/{id}', [\App\Http\Controllers\Api\Teacher\TaskController::class, 'destroy']);
        Route::get('tasks/{id}/submissions', [\App\Http\Controllers\Api\Teacher\TaskController::class, 'submissions']);
        Route::post('tasks/{id}/recipients', [\App\Http\Controllers\Api\Teacher\TaskController::class, 'addRecipients']);
        Route::patch('task-submissions/{id}/approve', [\App\Http\Controllers\Api\Teacher\TaskController::class, 'approveSubmission']);
        Route::patch('task-submissions/{id}/revision', [\App\Http\Controllers\Api\Teacher\TaskController::class, 'requestRevision']);

        // Attendance Verification
        Route::get('attendance', [\App\Http\Controllers\Api\Teacher\AttendanceVerificationController::class, 'pending']);
        Route::get('attendance/assignment/{assignmentId}', [\App\Http\Controllers\Api\Teacher\AttendanceVerificationController::class, 'byAssignment']);
        Route::get('attendance/statistics', [\App\Http\Controllers\Api\Teacher\AttendanceVerificationController::class, 'statistics']);
        Route::patch('attendance/{id}/approve', [\App\Http\Controllers\Api\Teacher\AttendanceVerificationController::class, 'approve']);
        Route::patch('attendance/{id}/reject', [\App\Http\Controllers\Api\Teacher\AttendanceVerificationController::class, 'reject']);
        Route::post('attendance/bulk-approve', [\App\Http\Controllers\Api\Teacher\AttendanceVerificationController::class, 'bulkApprove']);

        // Permission Management
        Route::get('permissions', [\App\Http\Controllers\Api\Teacher\PermissionController::class, 'index']);
        Route::patch('permissions/{id}/approve', [\App\Http\Controllers\Api\Teacher\PermissionController::class, 'approve']);
        Route::patch('permissions/{id}/reject', [\App\Http\Controllers\Api\Teacher\PermissionController::class, 'reject']);
    });

    // ========================================
    // MENTOR ROUTES
    // ========================================
    Route::middleware('role:mentor')->prefix('mentor')->group(function () {
        // Dashboard
        Route::get('dashboard', [\App\Http\Controllers\Api\Mentor\MentorDashboardController::class, 'index']);

        // Students / Siswa Bimbingan
        Route::get('students', [\App\Http\Controllers\Api\Mentor\MentorStudentController::class, 'index']);
        Route::get('students/{id}', [\App\Http\Controllers\Api\Mentor\MentorStudentController::class, 'show']);
        
        // Tasks
        Route::apiResource('tasks', \App\Http\Controllers\Api\Mentor\MentorTaskController::class);
        Route::patch('task-submissions/{id}/approve', [\App\Http\Controllers\Api\Mentor\MentorTaskController::class, 'approveSubmission']);
        Route::patch('task-submissions/{id}/revision', [\App\Http\Controllers\Api\Mentor\MentorTaskController::class, 'requestRevision']);
        
        // Reports
        Route::get('reports', [\App\Http\Controllers\Api\Mentor\MentorReportController::class, 'index']);
        Route::patch('reports/{id}/verify', [\App\Http\Controllers\Api\Mentor\MentorReportController::class, 'verify']);

        // Attendance
        Route::get('attendance/statistics', [\App\Http\Controllers\Api\Mentor\MentorAttendanceController::class, 'statistics']);
        Route::get('attendance/assignment/{assignmentId}', [\App\Http\Controllers\Api\Mentor\MentorAttendanceController::class, 'byAssignment']);
        Route::get('attendance', [\App\Http\Controllers\Api\Mentor\MentorAttendanceController::class, 'index']);

        // Permissions
        Route::get('permissions', [\App\Http\Controllers\Api\Mentor\MentorPermissionController::class, 'index']);
        Route::patch('permissions/{id}/approve', [\App\Http\Controllers\Api\Mentor\MentorPermissionController::class, 'approve']);
        Route::patch('permissions/{id}/reject', [\App\Http\Controllers\Api\Mentor\MentorPermissionController::class, 'reject']);
    });
});
