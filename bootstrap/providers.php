<?php

use App\Providers\AppServiceProvider;
use App\Providers\CallFeatureServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TelescopeServiceProvider;

return array_values(array_filter([
    AppServiceProvider::class,
    CallFeatureServiceProvider::class,
    FortifyServiceProvider::class,
    app()->isLocal() ? TelescopeServiceProvider::class : null,
]));
