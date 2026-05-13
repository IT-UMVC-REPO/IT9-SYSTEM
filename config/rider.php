<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rider Dispatch Settings
    |--------------------------------------------------------------------------
    |
    | RIDER_OFFER_TIMEOUT controls how long a rider has to respond to a
    | delivery offer. RIDER_MAX_CONCURRENT limits active picked-up or
    | out-for-delivery orders per rider, and RIDER_SEARCH_RADIUS_KM is used
    | when rider and vendor coordinates are known.
    |
    */

    'offer_timeout_seconds' => (int) env('RIDER_OFFER_TIMEOUT', 45),
    'max_concurrent_deliveries' => (int) env('RIDER_MAX_CONCURRENT', 3),
    'search_radius_km' => (float) env('RIDER_SEARCH_RADIUS_KM', 15),

    /*
    |--------------------------------------------------------------------------
    | Rider Earnings Settings
    |--------------------------------------------------------------------------
    |
    | RIDER_DELIVERY_FEE is the base payout for a delivered order. The distance
    | bonus is RIDER_PER_KM_BONUS multiplied by the vendor-to-customer distance,
    | capped by RIDER_MAX_EARNING so accidental long routes stay bounded.
    |
    */

    'delivery_fee' => (float) env('RIDER_DELIVERY_FEE', 50.00),
    'per_km_bonus' => (float) env('RIDER_PER_KM_BONUS', 5.00),
    'max_earning_per_delivery' => (float) env('RIDER_MAX_EARNING', 200.00),

];
