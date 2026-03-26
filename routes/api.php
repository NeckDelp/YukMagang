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
