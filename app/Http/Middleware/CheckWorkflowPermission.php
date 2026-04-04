<?php

namespace App\Http\Middleware;

use App\Models\Bulletin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWorkflowPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $bulletin = $request->route('bulletin');

        if ($bulletin instanceof Bulletin) {
            $requiredRole = $bulletin->status->requiredRole();

            if ($requiredRole && ! $request->user()?->hasAnyRole(['admin', $requiredRole])) {
                abort(403, 'Vous n\'avez pas les droits pour traiter ce bulletin.');
            }
        }

        return $next($request);
    }
}
