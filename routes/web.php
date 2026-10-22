<?php

use Illuminate\Support\Facades\Route;
use WursterMedien\Consent\Http\Controllers\AssetController;
use WursterMedien\Consent\Http\Controllers\StatusController;

Route::get('wm-consent/{file}', AssetController::class)
    ->where('file', 'wm-consent\.(js|css)')
    ->name('wm-consent.asset');

/* Ohne statamic.web und web: kein statischer Cache, keine Sitzung, kein CSRF (signiert, siehe StatusController). */
Route::post('wm-consent/status', StatusController::class)
    ->withoutMiddleware(['statamic.web', 'web'])
    ->name('wm-consent.status');
