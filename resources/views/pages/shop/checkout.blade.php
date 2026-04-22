@php
    $sections = [
        ['label' => 'Delivery', 'title' => 'Address and notes', 'description' => 'The real checkout page will later collect delivery address details and shopper notes.'],
        ['label' => 'Payment', 'title' => 'Method selection', 'description' => 'Cash on delivery, GCash, and Maya options can be presented here in the eventual form.'],
        ['label' => 'Review', 'title' => 'Order confirmation', 'description' => 'Final totals, item review, and submit confirmation belong in this layout once checkout is active.'],
        ['label' => 'Feedback', 'title' => 'Validation and completion', 'description' => 'The page should eventually guide shoppers through missing fields and successful order creation.'],
    ];

    $notes = [
        'The final checkout form needs to balance clarity, speed, and trust for first-time shoppers.',
        'Payment method messaging and order summary blocks will likely be the most important content here.',
        'A successful submit should later hand the customer into order tracking, not back into browsing.',
    ];

    $links = [
        ['label' => 'Cart shell', 'href' => route('shop.cart'), 'description' => 'Return to the cart placeholder.'],
        ['label' => 'Order history shell', 'href' => route('shop.orders'), 'description' => 'Open the future order list.'],
        ['label' => 'Messages inbox shell', 'href' => route('messages.inbox'), 'description' => 'Open the shared customer-vendor inbox page.'],
    ];
@endphp

<x-layouts::app :title="__('Checkout')">
    {{-- TODO: Replace this shell with delivery fields, payment selection, and order review content. --}}
    {{-- TODO: Add validation, payment-method explanations, and final submission handling. --}}
    {{-- TODO: Transition completed checkouts into order creation and tracking once the flow is built. --}}
    <x-placeholder-page
        eyebrow="Customer checkout"
        title="Checkout"
        description="This placeholder gives the customer purchase flow a real destination without turning on order creation logic yet."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>
