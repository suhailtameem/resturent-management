<?php

namespace App\Http\Middleware;

use App\Models\StaffUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $staffUser = $request->user();

        if (! $staffUser instanceof StaffUser || ! $staffUser->is_active || ! in_array($staffUser->role, $roles, true)) {
            return response()->json([
                'message' => 'You are not authorized to perform this action.',
                'code' => 'INSUFFICIENT_ROLE',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
