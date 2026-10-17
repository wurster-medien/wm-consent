<?php

use Illuminate\Support\Facades\Route;
use WursterMedien\Consent\Http\Controllers\CP\SettingsController;

Route::get('wm-consent', [SettingsController::class, 'edit'])->name('wm-consent.edit');
Route::patch('wm-consent', [SettingsController::class, 'update'])->name('wm-consent.update');
