<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFieldAuditorStaysInMobileLayout
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only enforce for authenticated users who have ONLY the field_auditor role
        if ($user && $user->hasRole('field_auditor') && $user->roles()->count() === 1) {
            $path = $request->path();

            // Allow /field routes, logout, assets, attachments and livewire internal endpoints
            if (
                str_starts_with($path, 'field') ||
                str_starts_with($path, 'livewire') ||
                str_starts_with($path, 'attachments') ||
                str_starts_with($path, 'verify') ||
                $request->is('logout')
            ) {
                return $next($request);
            }

            // Route mapping for /settings
            if (str_starts_with($path, 'settings')) {
                return redirect()->route('field.profile');
            }

            // Everything else (e.g. /dashboard, /audits, /processes, etc.) redirects to /field
            return redirect()->route('field.index');
        }

        return $next($request);
    }
}
