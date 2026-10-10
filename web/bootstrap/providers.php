<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModuleServiceProvider;

return [
    AppServiceProvider::class,
    // Loads every module's routes and wires the event listeners (System_Plan.md §4).
    ModuleServiceProvider::class,
];
