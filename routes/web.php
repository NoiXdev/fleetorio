<?php

use App\Http\Controllers\PublicOverviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicOverviewController::class)->name('home');
