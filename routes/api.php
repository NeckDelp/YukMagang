<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdmin\SchoolController as SuperAdminSchoolController;
use App\Http\Controllers\Api\School\StudentController;
use App\Http\Controllers\Api\School\TeacherController;
use App\Http\Controllers\Api\School\InternshipAssignmentController;
use App\Http\Controllers\Api\School\CompanyController;
use App\Http\Controllers\Api\School\DashboardController as SchoolDashboardController;
use App\Http\Controllers\Api\Student\DailyReportController;
use App\Http\Controllers\Api\Student\InternshipApplicationController;
use App\Http\Controllers\Api\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Api\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Api\Student\AttendanceController as StudentAttendanceController;
use App\Http\Controllers\Api\Student\PermissionController as StudentPermissionController;
use App\Http\Controllers\Api\Student\TaskSubmissionController;
use App\Http\Controllers\Api\Student\JobVacancyController;
use App\Http\Controllers\Api\Company\InternshipPositionController;
use App\Http\Controllers\Api\Company\ApplicationController as CompanyApplicationController;
use App\Http\Controllers\Api\Teacher\SupervisionController;
use App\Http\Controllers\Api\Teacher\TaskController as TeacherTaskController;
use App\Http\Controllers\Api\Teacher\AttendanceVerificationController;
use App\Http\Controllers\Api\Teacher\PermissionController as TeacherPermissionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']); // For initial setup only
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    // ========================================
    // SCHOOL ROUTES (Admin & Teacher)
    // ========================================
    Route::middleware(['role:school_admin,teacher', 'ensure.school.scope'])->prefix('school')->group(function () {

        // Dashboard
        Route::get('dashboard', [SchoolDashboardController::class, 'index']);

        // Students Management
        Route::apiResource('students', StudentController::class);

        // Teachers Management
        Route::apiResource('teachers', TeacherController::class);

        // Companies Management
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
    });

    // ========================================
    // STUDENT ROUTES
    // ========================================
    Route::middleware('role:student')->prefix('student')->group(function () {

        // Dashboard
        Route::get('dashboard', [StudentDashboardController::class, 'index']);

        // My Assignment
        Route::get('my-assignment', [StudentAssignmentController::class, 'myAssignment']);

        // Daily Reports
        Route::apiResource('daily-reports', DailyReportController::class)->except(['update']);
        Route::post('daily-reports/{id}', [DailyReportController::class, 'update']); // For file upload

        // Attendance
        Route::get('attendance/today', [StudentAttendanceController::class, 'today']);
        Route::post('attendance/clock-in', [StudentAttendanceController::class, 'clockIn']);
        Route::post('attendance/clock-out', [StudentAttendanceController::class, 'clockOut']);
        Route::get('attendance/history', [StudentAttendanceController::class, 'history']);
        Route::get('attendance/statistics', [StudentAttendanceController::class, 'statistics']);

        // Permissions (Izin)
        Route::get('permissions/statistics', [StudentPermissionController::class, 'statistics']);
        Route::get('permissions', [StudentPermissionController::class, 'index']);
        Route::post('permissions', [StudentPermissionController::class, 'store']);
        Route::get('permissions/{id}', [StudentPermissionController::class, 'show']);
        Route::delete('permissions/{id}', [StudentPermissionController::class, 'destroy']);

        // Task Submissions
        Route::get('tasks/statistics', [TaskSubmissionController::class, 'statistics']);
        Route::get('tasks', [TaskSubmissionController::class, 'index']);
        Route::get('tasks/{id}', [TaskSubmissionController::class, 'show']);
        Route::post('tasks/{id}/submit', [TaskSubmissionController::class, 'submit']);
        Route::patch('tasks/{id}/in-progress', [TaskSubmissionController::class, 'markInProgress']);

        // Job Vacancies
        Route::get('vacancies', [JobVacancyController::class, 'index']);
        Route::get('vacancies/{id}', [JobVacancyController::class, 'show']);

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
    // TEACHER / PEMBIMBING ROUTES
    // ========================================
    Route::middleware('role:teacher')->prefix('teacher')->group(function () {

        // Supervision Dashboard
        Route::get('dashboard', [SupervisionController::class, 'dashboard']);
        Route::get('supervision/assignments', [SupervisionController::class, 'myAssignments']);
        Route::get('supervision/by-company', [SupervisionController::class, 'groupedByCompany']);
        Route::get('supervision/by-major', [SupervisionController::class, 'groupedByMajor']);
        Route::get('supervision/company/{id}/students', [SupervisionController::class, 'companyStudents']);
        Route::post('supervision/bulk-approve-reports', [SupervisionController::class, 'bulkApproveDailyReports']);

        // Tasks
        Route::get('tasks', [TeacherTaskController::class, 'index']);
        Route::post('tasks', [TeacherTaskController::class, 'store']);
        Route::get('tasks/{id}', [TeacherTaskController::class, 'show']);
        Route::patch('tasks/{id}/approve', [TeacherTaskController::class, 'approve']);
        Route::patch('tasks/{id}/revision', [TeacherTaskController::class, 'requestRevision']);

        // Attendance Verification
        Route::get('attendance', [AttendanceVerificationController::class, 'index']);
        Route::get('attendance/statistics', [AttendanceVerificationController::class, 'statistics']);
        Route::patch('attendance/{id}/verify', [AttendanceVerificationController::class, 'verify']);

        // Permission Management
        Route::get('permissions', [TeacherPermissionController::class, 'index']);
        Route::patch('permissions/{id}/approve', [StudentPermissionController::class, 'approve']);
        Route::patch('permissions/{id}/reject', [StudentPermissionController::class, 'reject']);
    });

    // ========================================
    // COMPANY ROUTES
    // ========================================
    Route::middleware('role:company')->prefix('company')->group(function () {

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
    });
});
