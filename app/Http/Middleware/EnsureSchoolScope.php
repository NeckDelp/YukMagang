<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolScope
{
    /**
     * Handle an incoming request.
     * Ensures user can only access data from their school
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admin bypass
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        $schoolId = null;

        // ✅ PRIORITAS: relasi
        if ($user->role === 'teacher' && $user->teacher) {
            $schoolId = $user->teacher->school_id;
        } elseif ($user->role === 'student' && $user->student) {
            $schoolId = $user->student->school_id;
        }

        // ✅ FALLBACK: users table (untuk school_admin)
        elseif ($user->school_id) {
            $schoolId = $user->school_id;
        }

        if (!$schoolId) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with any school'
            ], 403);
        }

        $request->merge(['_school_id' => $schoolId]);

        return $next($request);
    }
}
