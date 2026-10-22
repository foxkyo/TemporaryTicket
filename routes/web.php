<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketPrintController;

Route::inertia('/', 'Welcome')->name('home');

Route::inertia('/tts', 'TemporaryTicket')->name('temporary-ticket');

Route::inertia('/vehicle-initial-counts', 'VehicleInitialCounts')->name('vehicle-initial-counts');

Route::match(['get', 'post'], '/ticket-print', TicketPrintController::class)->name('ticket-print');
