# 📚 Dokumentasi Arsitektur Multi-Tenant Lumino Backend

**Untuk Presentasi 30 Menit**

---

## 📋 Daftar Isi

1. [Overview Arsitektur](#1-overview-arsitektur)
2. [Migration Database (Pusat Tenant)](#2-migration-database-pusat-tenant)
3. [Middleware Tenant & Role](#3-middleware-tenant--role)
4. [Struktur Route API](#4-struktur-route-api)
5. [Model & Relasi](#5-model--relasi)
6. [Implementasi Controller](#6-implementasi-controller)
7. [Flow Request Handling](#7-flow-request-handling)

---

## 1. Overview Arsitektur

### Konsep Multi-Tenant

Lumino Backend menggunakan **Shared Database Multi-Tenancy** dengan dua jenis tenant:

- **Tenant Sekolah** (`schools`) - Untuk sekolah, siswa, dan guru
- **Tenant Perusahaan** (`companies`) - Untuk perusahaan dan lowongan magang

### Prinsip Desain

1. **Isolasi Data**: Setiap tenant hanya bisa mengakses data miliknya sendiri
2. **Role-Based Access**: Setiap user punya role yang menentukan akses
3. **Middleware Protection**: Middleware memastikan isolasi tenant di level route
4. **Foreign Key Constraints**: Database level protection dengan foreign keys

---

## 2. Migration Database (Pusat Tenant)

### 2.1. Migration: Tabel `schools`

**File**: `database/migrations/0000_12_31_235959_create_schools_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('npsn')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
```

**Penjelasan**:
- Setiap baris = **1 tenant sekolah**
- Kolom `id` menjadi referensi untuk `users.school_id`
- `status` untuk kontrol aktif/nonaktif tanpa hapus data

---

### 2.2. Migration: Tabel `companies`

**File**: `database/migrations/2026_01_06_014426_create_companies_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
```

**Penjelasan**:
- Setiap baris = **1 tenant perusahaan**
- Kolom `id` menjadi referensi untuk `users.company_id`
- Informasi bisnis untuk kebutuhan internship

---

### 2.3. Migration: Tabel `users` (Dengan Tenant ID)

**File**: `database/migrations/0001_01_01_000000_create_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('role', [
                'super_admin',
                'school_admin',
                'teacher',
                'student',
                'company'
            ]);
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
```

**Penjelasan**:
- `school_id` → Foreign key ke `schools` (untuk `school_admin`, `teacher`, `student`)
- `role` → Enum menentukan hak akses user
- `super_admin` tidak punya `school_id` (bisa akses semua)

---

### 2.4. Migration: Menambah `company_id` ke `users`

**File**: `database/migrations/2026_01_12_011420_add_company_id_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('school_id')
                ->constrained('companies')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
```

**Penjelasan**:
- Menambah kolom `company_id` untuk user dengan role `company`
- Foreign key dengan `cascade on delete` untuk integritas data

---

### 2.5. Migration: Tabel `students` (Terhubung ke Tenant)

**File**: `database/migrations/2026_01_06_014404_create_students_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('nis');
            $table->string('class');
            $table->string('major');
            $table->year('year');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
```

**Penjelasan**:
- `school_id` → Setiap siswa **harus** terikat ke 1 sekolah
- `user_id` → Relasi ke tabel `users`

---

### 2.6. Migration: Tabel `teachers` (Terhubung ke Tenant)

**File**: `database/migrations/2026_01_06_014405_create_teachers_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('nip')->nullable();
            $table->string('position')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
```

**Penjelasan**:
- `school_id` → Setiap guru **harus** terikat ke 1 sekolah
- `user_id` → Relasi ke tabel `users`

---

### 2.7. Migration: Tabel `internship_applications` (Multi-Tenant)

**File**: `database/migrations/2026_01_06_014633_create_internships_applications_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('internship_positions')->cascadeOnDelete();
            $table->enum('status', [
                'submitted',
                'approved_school',
                'rejected_school',
                'approved_company',
                'rejected_company',
                'active',
                'finished'
            ])->default('submitted');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internships_applications');
    }
};
```

**Penjelasan**:
- **Multi-tenant**: Punya `school_id` DAN `company_id`
- Status workflow: `submitted` → `approved_school` → `approved_company` → `active`

---

### 2.8. Migration: Tabel `internship_assignments` (Multi-Tenant)

**File**: `database/migrations/2026_01_06_014655_create_internships_assignments_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internships_assignments');
    }
};
```

**Penjelasan**:
- **Multi-tenant**: Punya `school_id` DAN `company_id`
- `supervisor_teacher_id` → Guru pembimbing dari sekolah

---

## 3. Middleware Tenant & Role

### 3.1. Middleware: `CheckRole`

**File**: `app/Http/Middleware/CheckRole.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * Mengecek apakah user memiliki role yang diizinkan
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $userRole = $request->user()->role;

        if (!in_array($userRole, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Required roles: ' . implode(', ', $roles)
            ], 403);
        }

        return $next($request);
    }
}
```

**Penjelasan**:
- Mengecek apakah `request->user()->role` ada dalam daftar `$roles`
- Jika tidak → return `401` (belum login) atau `403` (tidak punya akses)
- Digunakan di route: `middleware('role:school_admin,teacher')`

---

### 3.2. Middleware: `EnsureSchoolScope`

**File**: `app/Http/Middleware/EnsureSchoolScope.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolScope
{
    /**
     * Handle an incoming request.
     * Memastikan user hanya bisa akses data dari sekolahnya sendiri
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admin bisa akses semua sekolah
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Cek apakah user punya school_id
        if (!$user->school_id) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with any school'
            ], 403);
        }

        // Attach school_id ke request untuk mudah diakses di controller
        $request->merge(['_school_id' => $user->school_id]);

        return $next($request);
    }
}
```

**Penjelasan**:
- **Super admin** → Bypass (bisa akses semua)
- **User lain** → Harus punya `school_id`, kalau tidak → `403`
- Menambahkan `_school_id` ke `request` → Controller bisa pakai `request('_school_id')`

**Registrasi di `bootstrap/app.php` atau `app/Http/Kernel.php`**:
```php
'ensure.school.scope' => \App\Http\Middleware\EnsureSchoolScope::class,
```

---

## 4. Struktur Route API

### 4.1. File Route Lengkap

**File**: `routes/api.php`

```php
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
use App\Http\Controllers\Api\Company\InternshipPositionController;
use App\Http\Controllers\Api\Company\ApplicationController as CompanyApplicationController;

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
    // SUPER ADMIN ROUTES
    // ========================================
    Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
        Route::get('statistics', [SuperAdminSchoolController::class, 'statistics']);
        Route::apiResource('schools', SuperAdminSchoolController::class);
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
```

### 4.2. Penjelasan Route Grouping

#### **Group 1: Super Admin** (`/super-admin`)
- **Middleware**: `role:super_admin`
- **Akses**: Semua data dari semua sekolah
- **Route**: Kelola sekolah (CRUD), statistik global

#### **Group 2: School** (`/school`)
- **Middleware**: `['role:school_admin,teacher', 'ensure.school.scope']`
- **Akses**: Hanya data sekolah mereka sendiri
- **Route**: Kelola siswa, guru, perusahaan, assignments, daily reports

#### **Group 3: Student** (`/student`)
- **Middleware**: `role:student`
- **Akses**: Hanya data mereka sendiri
- **Route**: Assignment sendiri, daily reports, applications, browse companies/positions

#### **Group 4: Company** (`/company`)
- **Middleware**: `role:company`
- **Akses**: Hanya data perusahaan mereka sendiri
- **Route**: Profile, positions, applications, interns

---

## 5. Model & Relasi

### 5.1. Model `User`

**File**: `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'school_id',
        'company_id',
        'role',
        'name',
        'email',
        'password',
        'phone',
        'photo',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the school that owns the user.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the company that owns the user.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the student profile for the user.
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Get the teacher profile for the user.
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * Check if user is a company user
     */
    public function isCompany(): bool
    {
        return $this->role === 'company' && $this->company_id !== null;
    }

    /**
     * Check if user is a school user (admin/teacher/student)
     */
    public function isSchoolUser(): bool
    {
        return in_array($this->role, ['school_admin', 'teacher', 'student']) && $this->school_id !== null;
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
```

---

### 5.2. Model `School`

**File**: `app/Models/School.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    protected $table = 'schools';

    protected $fillable = [
        'name',
        'npsn',
        'address',
        'phone',
        'email',
        'logo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    /**
     * Get the users for the school.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the students for the school.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Get the teachers for the school.
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    /**
     * Get the internship applications for the school.
     */
    public function internshipApplications(): HasMany
    {
        return $this->hasMany(InternshipApplication::class);
    }

    /**
     * Get the internship assignments for the school.
     */
    public function internshipAssignments(): HasMany
    {
        return $this->hasMany(InternshipAssignment::class);
    }
}
```

---

### 5.3. Model `Company`

**File**: `app/Models/Company.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $fillable = [
        'name',
        'industry',
        'address',
        'description',
        'email',
        'phone',
        'website',
        'logo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    /**
     * Get the users (company admins) for the company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the internship positions for the company.
     */
    public function internshipPositions(): HasMany
    {
        return $this->hasMany(InternshipPosition::class);
    }

    /**
     * Get the internship applications for the company.
     */
    public function internshipApplications(): HasMany
    {
        return $this->hasMany(InternshipApplication::class);
    }

    /**
     * Get the internship assignments for the company.
     */
    public function internshipAssignments(): HasMany
    {
        return $this->hasMany(InternshipAssignment::class);
    }
}
```

---

## 6. Implementasi Controller

### 6.1. Controller: `Company\ApplicationController`

**File**: `app/Http/Controllers/Api/Company/ApplicationController.php`

```php
<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\InternshipApplication;
use App\Models\InternshipAssignment;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    /**
     * Get company_id from authenticated user
     */
    private function getCompanyId(Request $request)
    {
        return $request->user()->company_id;
    }

    /**
     * Display a listing of applications
     */
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $applications = InternshipApplication::where('company_id', $companyId)
            ->with(['student.user', 'position', 'school'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->position_id, fn($q, $id) => $q->where('position_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * Display the specified application
     */
    public function show(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $application = InternshipApplication::where('company_id', $companyId)
            ->where('id', $id)
            ->with([
                'student.user',
                'student.school',
                'position',
                'school'
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    /**
     * Approve application
     */
    public function approve(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $application = InternshipApplication::where('company_id', $companyId)
            ->where('id', $id)
            ->with('position')
            ->firstOrFail();

        // Check if already approved
        if ($application->status === 'approved_company') {
            return response()->json([
                'success' => false,
                'message' => 'Application already approved'
            ], 422);
        }

        // Check quota
        $acceptedCount = InternshipApplication::where('position_id', $application->position_id)
            ->where('status', 'approved_company')
            ->count();

        if ($acceptedCount >= $application->position->quota) {
            return response()->json([
                'success' => false,
                'message' => 'Position quota has been reached'
            ], 422);
        }

        $application->update(['status' => 'approved_company']);

        return response()->json([
            'success' => true,
            'message' => 'Application approved successfully',
            'data' => $application
        ]);
    }

    /**
     * Reject application
     */
    public function reject(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $application = InternshipApplication::where('company_id', $companyId)
            ->where('id', $id)
            ->firstOrFail();

        // Check if already processed
        if (in_array($application->status, ['approved_company', 'rejected_company'])) {
            return response()->json([
                'success' => false,
                'message' => 'Application has already been processed'
            ], 422);
        }

        $application->update(['status' => 'rejected_company']);

        return response()->json([
            'success' => true,
            'message' => 'Application rejected',
            'data' => $application
        ]);
    }

    /**
     * Get current interns
     */
    public function currentInterns(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $interns = InternshipAssignment::where('company_id', $companyId)
            ->where('status', 'active')
            ->with([
                'student.user',
                'student.school',
                'supervisorTeacher.user'
            ])
            ->withCount('dailyReports')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $interns
        ]);
    }

    /**
     * Get company profile
     */
    public function profile(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $company = Company::withCount([
            'internshipPositions',
            'internshipApplications',
            'internshipAssignments'
        ])->findOrFail($companyId);

        return response()->json([
            'success' => true,
            'data' => $company
        ]);
    }

    /**
     * Update company profile
     */
    public function updateProfile(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $company = Company::findOrFail($companyId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'industry' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $validated['logo'] = $request->file('logo')->store('companies/logos', 'public');
        }

        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Company profile updated successfully',
            'data' => $company
        ]);
    }
}
```

**Poin Penting**:
- **Selalu filter berdasarkan `company_id`** dari authenticated user
- Method `getCompanyId()` untuk konsistensi
- Query selalu pakai `where('company_id', $companyId)` untuk isolasi tenant

---

## 7. Flow Request Handling

### 7.1. Flow Request untuk Route `/school/students`

```
1. Request masuk → GET /api/school/students
   ↓
2. Middleware: auth:sanctum
   → Cek token, attach user ke request
   ↓
3. Middleware: role:school_admin,teacher
   → Cek apakah user.role ada di ['school_admin', 'teacher']
   → Jika tidak → 403 Unauthorized
   ↓
4. Middleware: ensure.school.scope
   → Cek user.school_id
   → Jika super_admin → bypass
   → Jika tidak punya school_id → 403
   → Attach _school_id ke request
   ↓
5. Controller: StudentController@index
   → Ambil request('_school_id')
   → Query: Student::where('school_id', $schoolId)->get()
   ↓
6. Response JSON dengan data siswa dari sekolah tersebut saja
```

### 7.2. Flow Request untuk Route `/company/applications`

```
1. Request masuk → GET /api/company/applications
   ↓
2. Middleware: auth:sanctum
   → Cek token, attach user ke request
   ↓
3. Middleware: role:company
   → Cek apakah user.role === 'company'
   → Jika tidak → 403 Unauthorized
   ↓
4. Controller: ApplicationController@index
   → Ambil user()->company_id
   → Query: InternshipApplication::where('company_id', $companyId)->get()
   ↓
5. Response JSON dengan data aplikasi dari perusahaan tersebut saja
```

---

## 8. Diagram Relasi Database

```
┌─────────────┐
│   schools   │
│─────────────│
│ id (PK)     │
│ name        │
│ npsn        │
│ status      │
└──────┬──────┘
       │
       │ 1:N
       │
┌──────▼──────┐         ┌──────────────┐
│    users    │         │  companies   │
│─────────────│         │──────────────│
│ id (PK)     │◄────────┤ id (PK)      │
│ school_id   │    N:1  │ name         │
│ company_id  │────────►│ industry     │
│ role        │    N:1  │ status       │
│ name        │         └──────────────┘
│ email       │
└──────┬──────┘
       │
       │ 1:1
       │
┌──────▼──────┐         ┌──────────────┐
│  students   │         │   teachers   │
│─────────────│         │──────────────│
│ id (PK)     │         │ id (PK)      │
│ user_id     │         │ user_id      │
│ school_id   │         │ school_id    │
│ nis         │         │ nip          │
└──────┬──────┘         └──────────────┘
       │
       │ 1:N
       │
┌──────▼──────────────────────────────────┐
│     internship_applications             │
│─────────────────────────────────────────│
│ id (PK)                                 │
│ student_id → students.id                │
│ school_id → schools.id                  │
│ company_id → companies.id              │
│ position_id → internship_positions.id   │
│ status                                  │
└─────────────────────────────────────────┘
```

---

## 9. Best Practices & Security

### 9.1. Prinsip Isolasi Tenant

1. **Selalu filter berdasarkan tenant ID**
   ```php
   // ✅ BENAR
   $data = Model::where('school_id', $schoolId)->get();
   
   // ❌ SALAH
   $data = Model::all(); // Bisa akses semua tenant!
   ```

2. **Gunakan middleware untuk proteksi route**
   ```php
   Route::middleware(['role:school_admin', 'ensure.school.scope'])
       ->prefix('school')
       ->group(function () {
           // Route hanya bisa diakses oleh school_admin dari sekolah mereka
       });
   ```

3. **Validasi di controller juga**
   ```php
   public function show($id)
   {
       $schoolId = request('_school_id');
       $item = Model::where('school_id', $schoolId)
           ->where('id', $id)
           ->firstOrFail(); // Otomatis 404 jika bukan milik sekolah mereka
   }
   ```

### 9.2. Security Checklist

- ✅ Middleware `auth:sanctum` untuk semua route protected
- ✅ Middleware `CheckRole` untuk validasi role
- ✅ Middleware `EnsureSchoolScope` untuk isolasi sekolah
- ✅ Foreign key constraints di database
- ✅ Query selalu filter berdasarkan tenant ID
- ✅ Validasi di controller level juga

---

## 10. Kesimpulan untuk Presentasi

### Poin Utama:

1. **Tenant Architecture**
   - Dua jenis tenant: `schools` dan `companies`
   - Setiap user terikat ke 1 tenant melalui `school_id` atau `company_id`

2. **Migration sebagai Pusat Tenant**
   - Tabel `schools` dan `companies` sebagai master tenant
   - Tabel `users` menghubungkan user ke tenant
   - Semua tabel terkait punya foreign key ke tenant

3. **Middleware Protection**
   - `CheckRole` → Validasi siapa yang boleh akses
   - `EnsureSchoolScope` → Validasi data sekolah mana yang boleh diakses

4. **Route Grouping**
   - Route dikelompokkan berdasarkan actor: `super-admin`, `school`, `student`, `company`
   - Setiap group punya middleware protection sendiri

5. **Controller Implementation**
   - Selalu filter berdasarkan tenant ID dari authenticated user
   - Query selalu pakai `where('tenant_id', $id)` untuk isolasi

---

**Dokumentasi ini siap untuk presentasi 30 menit!** 🎯

