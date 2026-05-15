<?php

namespace App\Http\Controllers;

use App\Models\VendorProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class LandingPageController extends Controller
{
    public function index(Request $request): View|Response
    {
        if ($this->isSocialPreviewCrawler($request)) {
            return response()
                ->view('social-preview')
                ->header('Cache-Control', 'public, max-age=300');
        }

        $featuredVendor = VendorProfile::query()
            ->approved()
            ->whereHas('products', fn (Builder $query): Builder => $query->active())
            ->withCount([
                'products as active_products_count' => fn (Builder $query): Builder => $query->active(),
            ])
            ->with([
                'user:id,name',
                'products' => fn ($query) => $query
                    ->active()
                    ->with('category:id,name')
                    ->latest()
                    ->limit(3),
            ])
            ->inRandomOrder()
            ->first();

        $featuredProducts = $featuredVendor?->products ?? collect();

        return view('welcome', [
            'featuredVendor' => $featuredVendor,
            'featuredProducts' => $featuredProducts,
        ]);
    }

    private function isSocialPreviewCrawler(Request $request): bool
    {
        $userAgent = Str::lower((string) $request->userAgent());

        return Str::contains($userAgent, [
            'facebookexternalhit',
            'facebot',
            'twitterbot',
            'linkedinbot',
            'slackbot',
            'discordbot',
        ]);
    }
}
