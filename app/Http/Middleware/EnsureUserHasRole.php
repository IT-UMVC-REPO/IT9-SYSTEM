<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
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

        $allowedRoles = $this->resolveAllowedRoles($roles);

        foreach ($allowedRoles as $role) {
            if ($user->canAccessMarketplaceRole($role)) {
                return $next($request);
            }
        }

        if ($user->effectiveMarketplaceRole() === UserRole::Admin && ! in_array(UserRole::Admin, $allowedRoles, true)) {
            $request->session()->flash('toast.warning', __('Admin accounts use the admin portal. Customer shopping actions are disabled for administrators.'));
        }

        if ($request->expectsJson()) {
            abort(403);
        }

        return redirect()->route($user->homeRoute());
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, UserRole>
     */
    private function resolveAllowedRoles(array $roles): array
    {
        return array_map(function (string $role): UserRole {
            $mappedRole = UserRole::tryFrom($role);

            if ($mappedRole === null) {
                throw new InvalidArgumentException("Unknown marketplace role [{$role}].");
            }

            return $mappedRole;
        }, $roles);
    }
}
