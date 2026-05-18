<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ContactMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function index(): View
    {
        return view('contact.index');
    }

    public function submit(): RedirectResponse
    {
        return redirect()->route('contact.index');
    }

    public function attachment(ContactMessage $contactMessage): StreamedResponse
    {
        abort_unless(auth()->user()?->canAccessMarketplaceRole(UserRole::Admin), 403);
        abort_if(blank($contactMessage->attachment_path), 404);

        return Storage::disk('private')->download($contactMessage->attachment_path);
    }
}
