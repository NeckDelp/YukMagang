<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdmin\SchoolController as SuperAdminSchoolController;
use App\Http\Controllers\Api\School\StudentController;
use App\Http\Controllers\Api\School\TeacherController;
use App\Http\Controllers\Api\School\InternshipAssignmentController;
use App\Http\Controllers\Api\School\CompanyController;
use App\Http\Controllers\Api\School\CompanyPartnershipController;
use App\Http\Controllers\Api\School\DashboardController as SchoolDashboardController;
use App\Http\Controllers\Api\Student\DailyReportController;
use App\Http\Controllers\Api\Student\InternshipApplicationController;
use App\Http\Controllers\Api\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Api\Student\AttendanceController;
use App\Http\Controllers\Api\Student\PermissionController as StudentPermissionController;
use App\Http\Controllers\Api\Student\DashboardController;
use App\Http\Controllers\Api\Student\JobVacancyController;
use App\Http\Controllers\Api\Company\InternshipPositionController;
use App\Http\Controllers\Api\Company\ApplicationController as CompanyApplicationController;
use App\Http\Controllers\Api\Teacher\SupervisionController;
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
    Route::post('/register-school', [AuthController::class, 'registerSchool']);
    Route::post('/register-school-admin', [AuthController::class, 'registerSchoolAdmin']);
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
    // SCHOOL ADMIN & TEACHER ROUTES
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

        // Company Partnership (School Admin only)
        Route::middleware('role:school_admin')->group(function () {
            Route::get('available-companies', [CompanyPartnershipController::class, 'available']);
            Route::post('companies/{id}/partner', [CompanyPartnershipController::class, 'partner']);
            Route::delete('companies/{id}/unpartner', [CompanyPartnershipController::class, 'unpartner']);
            Route::get('partnered-companies', [CompanyPartnershipController::class, 'partnered']);
        });

        // Internship Assignments
        Route::get('assignments/statistics', [InternshipAssignmentController::class, 'statistics']);
        Route::apiResource('assignments', InternshipAssignmentController::class);

        // Daily Reports (for viewing/approving by teacher)
        Route::get('daily-reports', [DailyReportController::class, 'indexForSchool']);
        Route::patch('daily-reports/{id}/approve', [DailyReportController::class, 'approve']);
        Route::patch('daily-reports/{id}/reject', [DailyReportController::class, 'reject']);

        // Internship Applications (for viewing by school)
        Route::get('applications', [InternshipApplicationController::class, 'indexForSchool']);

        // Teacher - Attendance Verification
        Route::middleware('role:teacher,school_admin')->prefix('teacher')->group(function () {
            Route::get('attendances/pending', [AttendanceVerificationController::class, 'pending']);
            Route::post('attendances/{id}/approve', [AttendanceVerificationController::class, 'approve']);
            Route::post('attendances/{id}/reject', [AttendanceVerificationController::class, 'reject']);
        });

        // Teacher - Permission Review
        Route::middleware('role:teacher,school_admin')->prefix('teacher')->group(function () {
            Route::get('permissions/pending', [TeacherPermissionController::class, 'pending']);
            Route::post('permissions/{id}/approve', [TeacherPermissionController::class, 'approve']);
            Route::post('permissions/{id}/reject', [TeacherPermissionController::class, 'reject']);
        });
    });

    // ========================================
    // STUDENT ROUTES
    // ========================================
    Route::middleware('role:student')->prefix('student')->group(function () {

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // My Assignment
        Route::get('my-assignment', [StudentAssignmentController::class, 'myAssignment']);

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

        // Attendance
        Route::get('attendance/today', [AttendanceController::class, 'today']);
        Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('attendance/history', [AttendanceController::class, 'history']);
        Route::get('attendance/statistics', [AttendanceController::class, 'statistics']);

        // Permission
        Route::get('permissions', [StudentPermissionController::class, 'index']);
        Route::post('permissions', [StudentPermissionController::class, 'store']);
        Route::get('permissions/{id}', [StudentPermissionController::class, 'show']);

        // Job Vacancies (untuk yang belum PKL / inactive students)
        Route::get('job-vacancies', [JobVacancyController::class, 'index']);
        Route::get('job-vacancies/{id}', [JobVacancyController::class, 'show']);
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

    // ========================================
    // TEACHER SPECIFIC ROUTES (Additional)
    // ========================================
    Route::middleware(['role:teacher', 'ensure.school.scope'])->prefix('teacher')->group(function () {

        // Dashboard
        Route::get('dashboard', [SupervisionController::class, 'dashboard']);

        // My supervised assignments
        Route::get('my-assignments', [SupervisionController::class, 'myAssignments']);

        // Grouped views
        Route::get('assignments/grouped-by-company', [SupervisionController::class, 'groupedByCompany']);
        Route::get('assignments/grouped-by-major', [SupervisionController::class, 'groupedByMajor']);

        // Company-specific students
        Route::get('companies/{company_id}/students', [SupervisionController::class, 'companyStudents']);

        // Bulk operations
        Route::post('daily-reports/bulk-approve', [SupervisionController::class, 'bulkApproveDailyReports']);
    });
});
