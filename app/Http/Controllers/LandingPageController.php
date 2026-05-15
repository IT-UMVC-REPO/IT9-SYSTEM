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
    private const ROBOTS_TXT = <<<'ROBOTS'
User-agent: facebookexternalhit
Allow: /

User-agent: Facebot
Allow: /

User-agent: meta-externalagent
Allow: /

User-agent: *
Allow: /
Disallow:
ROBOTS;

    public function index(Request $request): View|Response
    {
        if ($this->isSocialPreviewCrawler($request)) {
            return response()
                ->view('social-preview')
                ->header('Cache-Control', 'public, max-age=300')
                ->header('X-Suki-Social-Preview', '1');
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

    public function robots(): Response
    {
        return response(self::ROBOTS_TXT, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Suki-Robots' => '1',
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
