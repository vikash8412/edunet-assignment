<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // 404, not 403 — consistent with every other cross-tenant/cross-role
        // boundary in this feature (matches PublicFormController's existing
        // precedent of not confirming a resource's existence).
        abort_unless(
            $request->user() && in_array($request->user()->role, $roles, true),
            404,
        );

        return $next($request);
    }
}
