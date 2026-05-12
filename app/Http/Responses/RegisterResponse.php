<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToIntendedHome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    use RedirectsToIntendedHome;

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        /** @var Request $request */
        return $this->intendedHomeResponse($request, '', 201);
    }
}
