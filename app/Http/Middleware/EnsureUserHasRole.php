<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $allowedRoles = collect($roles)
            ->map(function (string $role): UserRole {
                $mappedRole = UserRole::tryFrom($role);

                if ($mappedRole === null) {
                    throw new InvalidArgumentException("Unknown marketplace role [{$role}].");
                }

                return $mappedRole;
            });

        if (! $allowedRoles->contains(fn (UserRole $role): bool => $user->hasMarketplaceRole($role))) {
            return new RedirectResponse(route($user->homeRoute()));
        }

        return $next($request);
    }
}
