<div class="brand-panel p-6 sm:p-8">
    @if ($submitted)
        <div class="text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300">
                <i class="fa-solid fa-check text-xl"></i>
            </span>
            <h2 class="brand-serif mt-5 text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Thank you for reaching out!') }}</h2>
            <p class="mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                {{ __("We've received your message and will get back to you within 1-2 business days. A confirmation has been sent to your email.") }}
            </p>
            <button type="button" wire:click="resetForm" class="brand-button-primary mt-6 active:scale-[0.96]">
                {{ __('Send another message') }}
            </button>
        </div>
    @else
        <form
            wire:submit="submit"
            class="space-y-6"
            x-data="{
                message: @entangle('message').live,
                fileName: '',
                previewUrl: null,
                isImage: false,
                updateFile(event) {
                    const file = event.target.files[0];

                    if (this.previewUrl) {
                        URL.revokeObjectURL(this.previewUrl);
                    }

                    if (! file) {
                        this.fileName = '';
                        this.previewUrl = null;
                        this.isImage = false;

                        return;
                    }

                    this.fileName = file.name;
                    this.isImage = file.type.startsWith('image/');
                    this.previewUrl = this.isImage ? URL.createObjectURL(file) : null;
                },
                removeFile() {
                    if (this.previewUrl) {
                        URL.revokeObjectURL(this.previewUrl);
                    }

                    this.previewUrl = null;
                    this.fileName = '';
                    this.isImage = false;
                    this.$refs.attachment.value = '';
                    $wire.$set('attachment', null);
                },
            }"
        >
            <input type="text" name="honeypot" wire:model="honeypot" class="hidden" aria-hidden="true" tabindex="-1" autocomplete="off">

            <flux:input wire:model="name" :label="__('Full Name')" type="text" required maxlength="100" autocomplete="name" />
            <flux:input wire:model="email" :label="__('Email Address')" type="email" required maxlength="150" autocomplete="email" />
            <flux:input wire:model="phone" :label="__('Phone Number')" type="tel" placeholder="+63 917 123 4567" autocomplete="tel" />

            <flux:select wire:model="subject" :label="__('Subject')" placeholder="{{ __('Choose an inquiry type') }}" required>
                <flux:select.option value="general_inquiry">{{ __('General Inquiry') }}</flux:select.option>
                <flux:select.option value="vendor_support">{{ __('Vendor Support') }}</flux:select.option>
                <flux:select.option value="rider_support">{{ __('Rider Support') }}</flux:select.option>
                <flux:select.option value="order_issue">{{ __('Order Issue') }}</flux:select.option>
                <flux:select.option value="report_user">{{ __('Report User') }}</flux:select.option>
                <flux:select.option value="billing">{{ __('Billing') }}</flux:select.option>
                <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
            </flux:select>

            <div>
                <flux:textarea wire:model.live="message" x-model="message" :label="__('Message')" rows="5" required maxlength="2000" />
                <p class="mt-1 text-xs text-neutral-500 dark:text-zinc-400">
                    <span x-text="message.length"></span>{{ __('/2000 characters') }}
                </p>
            </div>

            <div>
                <label for="contact-attachment" class="block text-sm font-semibold text-neutral-900 dark:text-zinc-100">
                    {{ __('Attach a file (optional) - Screenshots, documents (max 5MB).') }}
                </label>
                <input
                    x-ref="attachment"
                    id="contact-attachment"
                    type="file"
                    wire:model="attachment"
                    x-on:change="updateFile($event)"
                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                    class="mt-2 block w-full rounded-xl border border-stone-200 bg-stone-50 px-3.5 py-3 text-sm text-neutral-900 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-emerald-700 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-100 dark:file:bg-emerald-500/10 dark:file:text-emerald-300"
                >
                @error('attachment')
                    <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror

                <div x-show="fileName" x-cloak class="mt-3 rounded-2xl border border-stone-200 bg-stone-50 p-3 dark:border-white/10 dark:bg-zinc-800">
                    <template x-if="isImage && previewUrl">
                        <img :src="previewUrl" alt="{{ __('Attachment preview') }}" class="h-28 w-28 rounded-xl object-cover">
                    </template>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <p class="truncate text-sm font-medium text-neutral-700 dark:text-zinc-200" x-text="fileName"></p>
                        <button type="button" x-on:click="removeFile" class="text-sm font-semibold text-rose-600 dark:text-rose-300">
                            {{ __('Remove') }}
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="submit,attachment" class="brand-button-primary w-full active:scale-[0.96]">
                <span wire:loading.remove wire:target="submit">{{ __('Send Message') }}</span>
                <span wire:loading wire:target="submit">{{ __('Sending...') }}</span>
            </button>
        </form>
    @endif
</div>
