<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketPrintController extends Controller
{
    public function __invoke(Request $request)
    {
        // 取得最新一筆記錄的 barcode
        $latestTicket = \App\Models\TemporaryTicket::latest()->first();

        if (!$latestTicket) {
            return Inertia::render('TicketPrint', [
                'tickets' => [],
                'autoPrint' => $request->query('autoPrint', 'true') === 'true',
            ]);
        }

        // 提取 barcode 前5碼
        $barcodePrefix = substr($latestTicket->barcode, 0, 5);

        // 查詢所有 barcode 前5碼相同的記錄
        $tickets = \App\Models\TemporaryTicket::where('barcode', 'like', $barcodePrefix . '%')
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    '學號' => $ticket->學號,
                    '班級' => $ticket->班級,
                    '座號' => $ticket->座號,
                    '姓名' => $ticket->姓名,
                    '日期' => $ticket->日期->format('Y-m-d'),
                    '星期' => $ticket->星期,
                    '時段' => $ticket->時段,
                    '車別' => $ticket->車別,
                    '站別' => $ticket->站別,
                    'barcode' => $ticket->barcode,
                    '金額' => $ticket->金額,
                    'student' => [
                        '學號' => $ticket->學號,
                        '班級' => $ticket->班級,
                        '座號' => $ticket->座號,
                        '姓名' => $ticket->姓名,
                    ],
                    'station' => [
                        'vehicle' => $ticket->車別,
                        'station' => $ticket->站別,
                    ],
                ];
            })
            ->toArray();

        return Inertia::render('TicketPrint', [
            'tickets' => $tickets,
            'autoPrint' => $request->query('autoPrint', 'true') === 'true',
        ]);
    }
}
