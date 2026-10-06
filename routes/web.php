<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::inertia('/tts', 'TemporaryTicket')->name('temporary-ticket');

Route::inertia('/vehicle-initial-counts', 'VehicleInitialCounts')->name('vehicle-initial-counts');
