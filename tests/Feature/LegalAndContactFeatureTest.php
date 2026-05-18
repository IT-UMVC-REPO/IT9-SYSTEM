<?php

use App\Enums\UserRole;
use App\Events\ContactFormSubmitted;
use App\Jobs\NotifyAdminOfContactMessage;
use App\Jobs\SendContactConfirmationEmail;
use App\Livewire\ContactForm;
use App\Livewire\Pages\Vendor\Registration as VendorRegistration;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('legal pages are public and linked from registration and landing footer', function (): void {
    $this->get(route('legal.privacy-policy'))
        ->assertOk()
        ->assertSee('Data Privacy Act of 2012')
        ->assertSee('Republic Act No. 10173')
        ->assertSee('legal@sukimarket.ph');

    $this->get(route('legal.terms-and-conditions'))
        ->assertOk()
        ->assertSee('Philippine consumer protection')
        ->assertSee('Governing Law')
        ->assertSee('legal@sukimarket.ph');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('name="agree_terms"', false)
        ->assertSee(route('legal.terms-and-conditions'), false)
        ->assertSee(route('legal.privacy-policy'), false);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Terms &amp; Conditions', false)
        ->assertSee('Contact Us');
});

test('customer registration requires accepting legal terms', function (): void {
    $payload = [
        'name' => 'Legal Check',
        'email' => 'legal-check@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    $this->post(route('register.store'), $payload)
        ->assertSessionHasErrors(['agree_terms']);

    $this->post(route('register.store'), [
        ...$payload,
        'agree_terms' => 'on',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('customer.dashboard', absolute: false));
});

test('vendor and rider registrations require their terms of service agreement', function (): void {
    Storage::fake('public');

    $customer = User::factory()->create();
    $category = Category::factory()->standalone()->create();
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==');

    Livewire::actingAs($customer)
        ->test(VendorRegistration::class)
        ->set('store_name', 'Terms Stall')
        ->set('store_description', 'Fresh goods and clear seller responsibilities.')
        ->set('storeImageUpload', UploadedFile::fake()->createWithContent('stall.png', $png))
        ->set('sampleProducts.0.name', 'Pechay Bundle')
        ->set('sampleProducts.0.description', 'Fresh pechay for the morning market.')
        ->set('sampleProducts.0.price', '75.00')
        ->set('sampleProducts.0.stock_quantity', '8')
        ->set('sampleProducts.0.categoryId', (string) $category->getKey())
        ->set('sampleProductUploads.0', UploadedFile::fake()->createWithContent('pechay.png', $png))
        ->call('submit')
        ->assertHasErrors(['agree_vendor_tos' => 'accepted'])
        ->set('agree_vendor_tos', true)
        ->call('submit')
        ->assertHasNoErrors(['agree_vendor_tos']);

    $riderApplicant = User::factory()->create();

    Livewire::actingAs($riderApplicant)
        ->test('pages::rider.registration')
        ->set('vehicle_type', 'motorcycle')
        ->set('plate_number', 'ABC 1234')
        ->set('contact_number', '09171234567')
        ->call('submit')
        ->assertHasErrors(['agree_rider_tos' => 'accepted'])
        ->set('agree_rider_tos', true)
        ->call('submit')
        ->assertHasNoErrors(['agree_rider_tos']);
});

test('contact form stores the message and queues notifications', function (): void {
    Storage::fake('private');
    Event::fake([ContactFormSubmitted::class]);
    Queue::fake([SendContactConfirmationEmail::class, NotifyAdminOfContactMessage::class]);

    Livewire::test(ContactForm::class)
        ->set('name', 'Maria Santos')
        ->set('email', 'maria@example.com')
        ->set('phone', '+63 917 123 4567')
        ->set('subject', 'vendor_support')
        ->set('message', 'I need help updating my vendor application details please.')
        ->set('attachment', UploadedFile::fake()->createWithContent('screenshot.jpg', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==')))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $message = ContactMessage::query()->firstOrFail();

    expect($message->name)->toBe('Maria Santos')
        ->and($message->subject)->toBe('vendor_support')
        ->and($message->phone)->toBe('+639171234567')
        ->and($message->attachment_path)->toStartWith('contact-attachments/');

    Storage::disk('private')->assertExists($message->attachment_path);
    Event::assertDispatched(ContactFormSubmitted::class, fn (ContactFormSubmitted $event): bool => $event->contactMessage->is($message));
    Queue::assertPushed(SendContactConfirmationEmail::class);
    Queue::assertPushed(NotifyAdminOfContactMessage::class);
});

test('contact form silently ignores honeypot submissions and validates Philippine phone numbers', function (): void {
    Livewire::test(ContactForm::class)
        ->set('name', 'Spam Bot')
        ->set('email', 'spam@example.com')
        ->set('subject', 'general_inquiry')
        ->set('message', 'This message should be ignored because honeypot is filled.')
        ->set('honeypot', 'website')
        ->call('submit')
        ->assertHasNoErrors();

    expect(ContactMessage::query()->count())->toBe(0);

    Livewire::test(ContactForm::class)
        ->set('name', 'Phone Test')
        ->set('email', 'phone@example.com')
        ->set('phone', '12345')
        ->set('subject', 'general_inquiry')
        ->set('message', 'This message is long enough to pass the body validation.')
        ->call('submit')
        ->assertHasErrors(['phone' => 'regex']);
});

test('admins can view contact messages and mark them as read', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $contactMessage = ContactMessage::factory()->create([
        'name' => 'Unread Sender',
        'subject' => 'order_issue',
        'is_read' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.contact-messages'))
        ->assertOk()
        ->assertSee('Contact messages')
        ->assertSee('Unread Sender')
        ->assertSee('Order Issue');

    Livewire::actingAs($admin)
        ->test('pages::admin.contact-messages')
        ->call('selectMessage', $contactMessage->getKey())
        ->assertSee($contactMessage->message)
        ->call('markAsRead', $contactMessage->getKey())
        ->assertHasNoErrors();

    expect($contactMessage->refresh()->is_read)->toBeTrue()
        ->and($contactMessage->read_at)->not->toBeNull();
});
