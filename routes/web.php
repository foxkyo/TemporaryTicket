<?php

use App\Http\Controllers\TicketPrintController;
use Illuminate\Support\Facades\Route;

// Route::inertia('/', 'Welcome')->name('home');

// Route::inertia('/tts', 'TemporaryTicket')->name('temporary-ticket');
Route::inertia('/', 'TemporaryTicket')->name('home');

Route::inertia('/console', 'Console')->name('console');

Route::match(['get', 'post'], '/ticket-print', TicketPrintController::class)->name('ticket-print');
