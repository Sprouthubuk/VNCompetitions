<?php

use Illuminate\Support\Facades\Route;

// The storefront is the Astro app in web/. This host serves the API, /admin and /horizon.
Route::get('/', fn () => response()->json(['name' => config('app.name'), 'api' => url('/api/v1')]));
