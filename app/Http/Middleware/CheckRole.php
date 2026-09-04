<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware('role:admin,gestionnaire')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        // super_admin passe toujours — il a accès à tout
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        if (! in_array($user->role, $roles, strict: true)) {
            return response()->json([
                'message'        => 'Accès refusé. Votre rôle ne permet pas cette action.',
                'required_roles' => $roles,
                'current_role'   => $user->role,
            ], 403);
        }

        return $next($request);
    }
}