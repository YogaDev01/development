<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param  string  ...$roles
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Ensure user is authenticated
        if (!Auth::check()) {
            return redirect('/login');
        }

        /** @var User $user */
        $user = Auth::user();

        // Ensure user is loaded with role relation
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        // Ensure user has a role assigned
        if (!$user->role) {
            abort(403, 'Unauthorized - No role assigned');
        }

        // Check if user's role is in the allowed roles
        if (!$user->hasRole($roles)) {
            abort(403, 'Unauthorized - Insufficient permissions');
        }

        return $next($request);
    }
}