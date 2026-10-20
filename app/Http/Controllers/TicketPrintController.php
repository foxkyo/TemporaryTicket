<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketPrintController extends Controller
{
    public function __invoke(Request $request)
    {
        $ticketsParam = $request->query('tickets');

        if ($ticketsParam) {
            try {
                $tickets = json_decode(urldecode($ticketsParam), true);
                $tickets = is_array($tickets) ? $tickets : [];
            } catch (\Exception $e) {
                $tickets = [];
            }
        } else {
            $tickets = [];
        }

        return Inertia::render('TicketPrint', [
            'tickets' => $tickets,
        ]);
    }
}
