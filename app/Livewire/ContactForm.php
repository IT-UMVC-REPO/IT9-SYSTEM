<?php

namespace App\Livewire;

use App\Events\ContactFormSubmitted;
use App\Jobs\NotifyAdminOfContactMessage;
use App\Jobs\SendContactConfirmationEmail;
use App\Models\ContactMessage;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ContactForm extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $subject = '';

    public string $message = '';

    public ?TemporaryUploadedFile $attachment = null;

    public string $honeypot = '';

    public bool $submitted = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'regex:/^(09|\+639)\d{9}$/'],
            'subject' => ['required', 'in:general_inquiry,vendor_support,rider_support,order_issue,report_user,billing,other'],
            'message' => ['required', 'string', 'min:20', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'phone.regex' => __('Please enter a valid Philippine mobile number.'),
            'subject.in' => __('Please select a valid inquiry type.'),
            'message.min' => __('Your message must be at least 20 characters.'),
        ];
    }

    public function submit(): void
    {
        if (filled($this->honeypot)) {
            return;
        }

        $ipAddress = request()->ip();
        $userAgent = request()->userAgent();
        $this->phone = $this->normalizePhoneForValidation($this->phone);
        $validated = $this->validate();

        $attachmentPath = $this->attachment?->store('contact-attachments', 'private');

        $contactMessage = ContactMessage::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => blank($validated['phone'] ?? null) ? null : $validated['phone'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'attachment_path' => $attachmentPath,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        event(new ContactFormSubmitted($contactMessage));

        SendContactConfirmationEmail::dispatch($contactMessage->getKey());
        NotifyAdminOfContactMessage::dispatch($contactMessage->getKey());

        $this->reset('name', 'email', 'phone', 'subject', 'message', 'attachment', 'honeypot');
        $this->submitted = true;
    }

    public function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset('name', 'email', 'phone', 'subject', 'message', 'attachment', 'honeypot');
        $this->submitted = false;
    }

    public function render(): View
    {
        return view('livewire.contact-form');
    }

    private function normalizePhoneForValidation(string $phone): string
    {
        return preg_replace('/[\s().-]+/', '', trim($phone)) ?? trim($phone);
    }
}
