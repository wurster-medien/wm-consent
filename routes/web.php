<?php

use Illuminate\Support\Facades\Route;
use WursterMedien\Consent\Http\Controllers\AssetController;

Route::get('wm-consent/{file}', AssetController::class)
    ->where('file', 'wm-consent\.(js|css)')
    ->name('wm-consent.asset');
