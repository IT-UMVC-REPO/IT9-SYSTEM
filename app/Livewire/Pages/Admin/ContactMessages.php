<?php

namespace App\Livewire\Pages\Admin;

use App\Models\ContactMessage;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Contact messages')]
class ContactMessages extends Component
{
    use WithPagination;

    public ?int $selectedMessageId = null;

    public function selectMessage(int $contactMessageId): void
    {
        $this->selectedMessageId = $contactMessageId;
    }

    public function markAsRead(int $contactMessageId): void
    {
        ContactMessage::query()->findOrFail($contactMessageId)->markAsRead();
    }

    #[Computed]
    public function selectedMessage(): ?ContactMessage
    {
        if ($this->selectedMessageId === null) {
            return null;
        }

        return ContactMessage::query()->find($this->selectedMessageId);
    }

    public function render(): View
    {
        return view('pages::admin.⚡contact-messages', [
            'messages' => ContactMessage::query()
                ->latest('created_at')
                ->paginate(10),
        ]);
    }
}
