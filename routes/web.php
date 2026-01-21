<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocationController;

Route::get('/', [LocationController::class, 'index'])->name('location.index');

Route::post('/location/update', [LocationController::class, 'update'])->name('location.update');