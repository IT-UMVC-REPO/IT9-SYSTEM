<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsToIntendedHome
{
    protected function intendedHomeResponse(
        Request $request,
        array|string $jsonPayload,
        int $jsonStatus = 200,
    ): JsonResponse|RedirectResponse {
        if ($request->wantsJson()) {
            return new JsonResponse($jsonPayload, $jsonStatus);
        }

        return redirect()->intended(
            route($request->user()->homeRoute(), absolute: false),
        );
    }
}
