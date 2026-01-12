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

        // Super admin can access all schools
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Check if user has school_id
        if (!$user->school_id) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with any school'
            ], 403);
        }

        // Attach school_id to request for easy access in controllers
        $request->merge(['_school_id' => $user->school_id]);

        return $next($request);
    }
}
