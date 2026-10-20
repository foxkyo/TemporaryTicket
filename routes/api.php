<?php

use App\Models\Station;
use App\Models\Student;
use App\Models\TemporaryTicket;
use App\Models\TemporaryTicketControl;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/students', function () {
    $gradeOrder = [
        '國一' => 1,
        '國二' => 2,
        '國三' => 3,
        '高一' => 4,
        '高二' => 5,
        '高三' => 6,
    ];

    $classOrder = [
        '1' => 1,
        '2' => 2,
        '3' => 3,
        '4' => 4,
        '5' => 5,
        '6' => 6,
        '7' => 7,
        '8' => 8,
        '9' => 9,
        '10' => 10,
        '甲' => 11,
        '乙' => 12,
        '丙' => 13,
        '丁' => 14,
        '戊' => 15,
        '己' => 16,
        '庚' => 17,
    ];

    $students = Student::query()
        ->orderBy('座號')
        ->get();

    $result = [];

    foreach ($students as $student) {
        $grade = mb_substr($student->班級, 0, 2);
        $class = mb_substr($student->班級, 2);

        $result[$grade]['classes'][$class]['students'][] = [
            'id' => $student->id,
            '學號' => $student->學號,
            '班級' => $student->班級,
            '座號' => $student->座號,
            '姓名' => $student->姓名,
        ];
    }

    uksort($result, function ($a, $b) use ($gradeOrder) {
        return ($gradeOrder[$a] ?? 999) <=> ($gradeOrder[$b] ?? 999);
    });

    foreach ($result as $grade => &$gradeData) {
        uksort($gradeData['classes'], function ($a, $b) use ($classOrder) {
            return ($classOrder[$a] ?? 999) <=> ($classOrder[$b] ?? 999);
        });
    }

    return response()->json(
        collect($result)->map(function ($gradeData, $grade) {
            return [
                'grade' => $grade,
                'classes' => collect($gradeData['classes'])
                    ->map(function ($classData, $class) {
                        return [
                            'class' => $class,
                            'students' => $classData['students'],
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        })->values()
    );
});

Route::get('/temporary-tickets/latest-for-student', function () {
    $query = request()->validate([
        '學號' => ['required', 'string'],
        '班級' => ['required', 'string'],
        '姓名' => ['required', 'string'],
    ]);

    $ticket = TemporaryTicket::query()
        ->with('station')
        ->where('學號', $query['學號'])
        ->where('班級', $query['班級'])
        ->where('姓名', $query['姓名'])
        ->orderByDesc('日期')
        ->orderByDesc('id')
        ->first();

    if (! $ticket) {
        return response()->json([
            'ticket' => null,
        ]);
    }

    $periods = is_array($ticket->時段)
        ? $ticket->時段
        : (json_decode($ticket->時段, true) ?? []);

    return response()->json([
        'ticket' => [
            'id' => $ticket->id,
            '學號' => $ticket->學號,
            '班級' => $ticket->班級,
            '姓名' => $ticket->姓名,
            '日期' => $ticket->日期?->format('Y-m-d'),
            '星期' => $ticket->星期,
            '時段' => $periods,
            '車別' => $ticket->車別,
            '站別' => $ticket->站別,
            'barcode' => $ticket->barcode,
            'station' => $ticket->station ? [
                'id' => $ticket->station->id,
                'vehicle' => $ticket->station->車別,
                'station' => $ticket->station->站別,
                'boarding' => $ticket->station->乘車處,
                'one_way_amount' => (int) $ticket->station->單趟金額,
                'one_month_amount' => (int) $ticket->station->單月金額,
            ] : null,
        ],
    ]);
});

Route::get('/stations', function () {
    $stations = Station::query()
        ->orderBy('id')
        ->get();

    if ($stations->isEmpty()) {
        return response()->json([]);
    }

    $vehicles = [];
    $stationsByVehicle = [];
    $vehicleOrder = [];

    foreach ($stations as $station) {
        $vehicle = $station->車別;

        if (! isset($vehicles[$vehicle])) {
            $vehicles[$vehicle] = [
                'vehicle' => $vehicle,
                'capacity' => (int) ($station->人數上限 ?? 0),
                '上學' => [
                    'student_count' => 0,
                    'temporary_ticket_count' => 0,
                    'total' => 0,
                    'remaining' => 0,
                    'status' => 'normal',
                    '管制' => false,
                ],
                '放學' => [
                    'student_count' => 0,
                    'temporary_ticket_count' => 0,
                    'total' => 0,
                    'remaining' => 0,
                    'status' => 'normal',
                    '管制' => false,
                ],
            ];

            $vehicleOrder[$vehicle] = (int) $station->id;
        }

        $stationsByVehicle[$vehicle][] = [
            'id' => $station->id,
            'station' => $station->站別,
            'boarding' => $station->乘車處,
            'one_way_amount' => (int) $station->單趟金額,
            'one_month_amount' => (int) $station->單月金額,
            'vehicle' => $vehicle,
        ];

        $vehicles[$vehicle]['上學']['student_count'] += (int) ($station->上學人數 ?? 0);
        $vehicles[$vehicle]['放學']['student_count'] += (int) ($station->放學人數 ?? 0);
    }

    $vehicleNames = array_keys($vehicles);

    $temporaryTickets = TemporaryTicket::query()
        ->whereIn('車別', $vehicleNames)
        ->get([
            '車別',
            '時段',
        ]);

    $temporaryTicketCounts = [];

    foreach ($temporaryTickets as $ticket) {
        $periods = is_array($ticket->時段)
            ? $ticket->時段
            : json_decode($ticket->時段, true);

        if (! is_array($periods)) {
            continue;
        }

        foreach ($periods as $period) {
            if (! in_array($period, ['上學', '放學'], true)) {
                continue;
            }

            $temporaryTicketCounts[$ticket->車別][$period] =
                ($temporaryTicketCounts[$ticket->車別][$period] ?? 0) + 1;
        }
    }

    $controls = TemporaryTicketControl::query()
        ->whereIn('車別', $vehicleNames)
        ->get()
        ->keyBy('車別');

    $result = [];

    foreach ($vehicles as $vehicle => $data) {
        $capacity = $data['capacity'];
        $morningTotal = $data['上學']['student_count'] + ($temporaryTicketCounts[$vehicle]['上學'] ?? 0);
        $afternoonTotal = $data['放學']['student_count'] + ($temporaryTicketCounts[$vehicle]['放學'] ?? 0);
        $control = $controls->get($vehicle);

        $data['上學']['temporary_ticket_count'] = $temporaryTicketCounts[$vehicle]['上學'] ?? 0;
        $data['上學']['total'] = $morningTotal;
        $data['上學']['remaining'] = max(0, $capacity - $morningTotal);
        $data['上學']['status'] = $morningTotal > $capacity
            ? 'danger'
            : ($data['上學']['remaining'] <= 3 ? 'warning' : 'normal');
        $data['上學']['管制'] = (bool) ($control?->上學管制 ?? false);

        $data['放學']['temporary_ticket_count'] = $temporaryTicketCounts[$vehicle]['放學'] ?? 0;
        $data['放學']['total'] = $afternoonTotal;
        $data['放學']['remaining'] = max(0, $capacity - $afternoonTotal);
        $data['放學']['status'] = $afternoonTotal > $capacity
            ? 'danger'
            : ($data['放學']['remaining'] <= 3 ? 'warning' : 'normal');
        $data['放學']['管制'] = (bool) ($control?->放學管制 ?? false);

        $data['stations'] = $stationsByVehicle[$vehicle] ?? [];

        $result[] = $data;
    }

    usort($result, function ($a, $b) use ($vehicleOrder) {
        return ($vehicleOrder[$a['vehicle']] ?? PHP_INT_MAX)
            <=> ($vehicleOrder[$b['vehicle']] ?? PHP_INT_MAX);
    });

    $areas = [
        '嘉義縣市' => [],
        '其他' => [],
    ];

    foreach ($result as $vehicle) {
        $area = mb_substr($vehicle['vehicle'], 0, 1) === '嘉'
            ? '嘉義縣市'
            : '其他';

        $areas[$area][] = $vehicle;
    }

    return response()->json([
        [
            'area' => '嘉義縣市',
            'vehicles' => $areas['嘉義縣市'],
        ],
        [
            'area' => '其他',
            'vehicles' => $areas['其他'],
        ],
    ]);
});

Route::get('/vehicle-initial-counts', function () {
    $totals = Station::query()
        ->select(['id', '車別', '上學人數', '放學人數'])
        ->get()
        ->groupBy('車別')
        ->map(function ($rows, $vehicle) {
            return [
                'id' => (int) $rows->min('id'),
                'vehicle' => $vehicle,
                '車別' => $vehicle,
                '上學人數' => (int) $rows->sum('上學人數'),
                '放學人數' => (int) $rows->sum('放學人數'),
            ];
        })
        ->values()
        ->all();

    usort($totals, fn (array $left, array $right) => ($left['id'] ?? 0) <=> ($right['id'] ?? 0));

    return response()->json($totals);
});

Route::put('/vehicle-initial-counts/{vehicle}', function (string $vehicle) {
    $data = request()->validate([
        '上學人數' => ['required', 'integer', 'min:0'],
        '放學人數' => ['required', 'integer', 'min:0'],
    ]);

    $stations = Station::query()
        ->where('車別', $vehicle)
        ->orderBy('id')
        ->get();

    if ($stations->isEmpty()) {
        return response()->json([
            'message' => '找不到對應車別',
        ], 404);
    }

    $count = $stations->count();
    $morningBase = intdiv((int) $data['上學人數'], $count);
    $morningRemainder = (int) $data['上學人數'] % $count;
    $afternoonBase = intdiv((int) $data['放學人數'], $count);
    $afternoonRemainder = (int) $data['放學人數'] % $count;

    $stations->each(function ($station, $index) use ($morningBase, $morningRemainder, $afternoonBase, $afternoonRemainder) {
        $station->update([
            '上學人數' => $morningBase + ($index < $morningRemainder ? 1 : 0),
            '放學人數' => $afternoonBase + ($index < $afternoonRemainder ? 1 : 0),
        ]);
    });

    return response()->json([
        'vehicle' => $vehicle,
        '車別' => $vehicle,
        '上學人數' => (int) $data['上學人數'],
        '放學人數' => (int) $data['放學人數'],
    ]);
});

Route::post('/vehicle-initial-counts/import', function () {
    $payload = request()->validate([
        'rows' => ['required', 'array'],
    ]);

    $updated = [];

    foreach ($payload['rows'] as $row) {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        $vehicle = trim((string) ($normalized['車別'] ?? $normalized['vehicle'] ?? $normalized['車種'] ?? ''));

        if ($vehicle === '') {
            continue;
        }

        $morning = $normalized['上學人數'] ?? $normalized['上學'] ?? $normalized['morning'] ?? null;
        $afternoon = $normalized['放學人數'] ?? $normalized['放學'] ?? $normalized['afternoon'] ?? null;
        $singleCount = $normalized['人數'] ?? $normalized['count'] ?? $normalized['total'] ?? null;

        if ($morning === null && $singleCount !== null) {
            $morning = $singleCount;
        }

        if ($afternoon === null && $singleCount !== null) {
            $afternoon = $singleCount;
        }

        $morningValue = (int) ($morning ?? 0);
        $afternoonValue = (int) ($afternoon ?? 0);

        $stations = Station::query()
            ->where('車別', $vehicle)
            ->orderBy('id')
            ->get();

        if ($stations->isEmpty()) {
            continue;
        }

        $count = $stations->count();
        $morningBase = intdiv($morningValue, $count);
        $morningRemainder = $morningValue % $count;
        $afternoonBase = intdiv($afternoonValue, $count);
        $afternoonRemainder = $afternoonValue % $count;

        $stations->each(function ($station, $index) use ($morningBase, $morningRemainder, $afternoonBase, $afternoonRemainder) {
            $station->update([
                '上學人數' => $morningBase + ($index < $morningRemainder ? 1 : 0),
                '放學人數' => $afternoonBase + ($index < $afternoonRemainder ? 1 : 0),
            ]);
        });

        $updated[] = [
            'vehicle' => $vehicle,
            '車別' => $vehicle,
            '上學人數' => $morningValue,
            '放學人數' => $afternoonValue,
        ];
    }

    return response()->json([
        'updated' => $updated,
    ]);
});

Route::get('/stations/availability', function () {
    $stations = Station::query()
        ->orderBy('id')
        ->get();

    if ($stations->isEmpty()) {
        return response()->json([]);
    }

    /*
     * 每台車以第一筆 station 的「人數上限」作為容量。
     */
    $vehicles = [];

    foreach ($stations as $station) {
        $vehicle = $station->車別;

        if (! isset($vehicles[$vehicle])) {
            $vehicles[$vehicle] = [
                'first_id' => $station->id,
                'vehicle' => $vehicle,
                'capacity' => (int) $station->人數上限,
                '上學人數' => 0,
                '放學人數' => 0,
            ];
        }

        /*
         * stations 的人數是各站別人數，因此同車別加總。
         */
        $vehicles[$vehicle]['上學人數'] += (int) $station->上學人數;
        $vehicles[$vehicle]['放學人數'] += (int) $station->放學人數;
    }

    /*
     * 取得所有車別的臨時票。
     */
    $vehicleNames = collect($vehicles)
        ->pluck('vehicle')
        ->values();

    $temporaryTickets = TemporaryTicket::query()
        ->whereIn('車別', $vehicleNames)
        ->get([
            '車別',
            '時段',
        ]);

    $temporaryTicketCounts = [];

    foreach ($temporaryTickets as $ticket) {
        $periods = is_array($ticket->時段)
            ? $ticket->時段
            : json_decode($ticket->時段, true);

        if (! is_array($periods)) {
            continue;
        }

        foreach ($periods as $period) {
            if (! in_array($period, ['上學', '放學'], true)) {
                continue;
            }

            $temporaryTicketCounts[$ticket->車別][$period] =
                ($temporaryTicketCounts[$ticket->車別][$period] ?? 0) + 1;
        }
    }

    /*
     * 取得車別管制狀態。
     */
    $controls = TemporaryTicketControl::query()
        ->whereIn('車別', $vehicleNames)
        ->get()
        ->keyBy('車別');

    $result = [];

    foreach ($vehicles as $vehicle => $data) {
        $capacity = $data['capacity'];

        $morningTemporary =
            $temporaryTicketCounts[$vehicle]['上學'] ?? 0;

        $afternoonTemporary =
            $temporaryTicketCounts[$vehicle]['放學'] ?? 0;

        $morningTotal =
            $data['上學人數'] + $morningTemporary;

        $afternoonTotal =
            $data['放學人數'] + $afternoonTemporary;

        $control = $controls->get($vehicle);

        $morningRemaining = $capacity - $morningTotal;
        $afternoonRemaining = $capacity - $afternoonTotal;

        $result[] = [
            'vehicle' => $vehicle,
            'capacity' => $capacity,

            '上學' => [
                'student_count' => $data['上學人數'],
                'temporary_ticket_count' => $morningTemporary,
                'total' => $morningTotal,
                'remaining' => max(0, $morningRemaining),

                /*
                 * danger：超過上限
                 * warning：距離上限 3 人以內
                 * normal：其他
                 */
                'status' => $morningTotal > $capacity
                    ? 'danger'
                    : ($morningRemaining <= 3
                        ? 'warning'
                        : 'normal'),

                '管制' => (bool) ($control?->上學管制 ?? false),
            ],

            '放學' => [
                'student_count' => $data['放學人數'],
                'temporary_ticket_count' => $afternoonTemporary,
                'total' => $afternoonTotal,
                'remaining' => max(0, $afternoonRemaining),

                'status' => $afternoonTotal > $capacity
                    ? 'danger'
                    : ($afternoonRemaining <= 3
                        ? 'warning'
                        : 'normal'),

                '管制' => (bool) ($control?->放學管制 ?? false),
            ],
        ];
    }

    /*
     * 依原本 stations 第一筆 id 排序。
     */
    usort($result, function ($a, $b) use ($vehicles) {
        return $vehicles[$a['vehicle']]['first_id']
            <=> $vehicles[$b['vehicle']]['first_id'];
    });

    /*
     * 嘉義縣市 / 其他。
     */
    $areas = [
        '嘉義縣市' => [],
        '其他' => [],
    ];

    foreach ($result as $vehicle) {
        $area = mb_substr($vehicle['vehicle'], 0, 1) === '嘉'
            ? '嘉義縣市'
            : '其他';

        $areas[$area][] = $vehicle;
    }

    return response()->json([
        [
            'area' => '嘉義縣市',
            'vehicles' => $areas['嘉義縣市'],
        ],
        [
            'area' => '其他',
            'vehicles' => $areas['其他'],
        ],
    ]);
});

/*
|--------------------------------------------------------------------------
| Temporary Ticket Controls
|--------------------------------------------------------------------------
*/

Route::get('/temporary-ticket-controls', function () {
    $stations = Station::query()
        ->orderBy('id')
        ->get();

    $controls = TemporaryTicketControl::query()
        ->get()
        ->keyBy('車別');

    $vehicles = [];

    foreach ($stations as $station) {
        $vehicle = $station->車別;

        if (isset($vehicles[$vehicle])) {
            continue;
        }

        $control = $controls->get($vehicle);

        $vehicles[$vehicle] = [
            'vehicle' => $vehicle,
            'capacity' => (int) $station->人數上限,
            '上學管制' => (bool) ($control?->上學管制 ?? false),
            '放學管制' => (bool) ($control?->放學管制 ?? false),
        ];
    }

    return response()->json(
        collect($vehicles)->values()
    );
});

Route::get('/temporary-tickets', function () {
    $tickets = TemporaryTicket::query()
        ->with('station')
        ->orderBy('日期')
        ->orderBy('id')
        ->get()
        ->map(function (TemporaryTicket $ticket) {
            return [
                'id' => $ticket->id,
                '學號' => $ticket->學號,
                '班級' => $ticket->班級,
                '座號' => $ticket->座號,
                '姓名' => $ticket->姓名,
                '日期' => $ticket->日期?->format('Y-m-d'),
                '星期' => $ticket->星期,
                '時段' => is_array($ticket->時段)
                    ? $ticket->時段
                    : (json_decode($ticket->時段, true) ?? []),
                '車別' => $ticket->車別,
                '站別' => $ticket->站別,
                'barcode' => $ticket->barcode,
                '金額' => (int) $ticket->金額,
                'station' => $ticket->station ? [
                    'id' => $ticket->station->id,
                    'vehicle' => $ticket->station->車別,
                    'station' => $ticket->station->站別,
                    'one_way_amount' => (int) $ticket->station->單趟金額,
                ] : null,
            ];
        })
        ->values()
        ->all();

    return response()->json([
        'tickets' => $tickets,
    ]);
});

Route::post('/temporary-tickets', function () {
    $payload = request()->validate([
        'tickets' => ['required', 'array', 'min:1'],
        'tickets.*.student' => ['required', 'array'],
        'tickets.*.student.學號' => ['required', 'string'],
        'tickets.*.student.班級' => ['required', 'string'],
        'tickets.*.student.座號' => ['required'],
        'tickets.*.student.姓名' => ['required', 'string'],
        'tickets.*.station' => ['required', 'array'],
        'tickets.*.station.vehicle' => ['required', 'string'],
        'tickets.*.station.station' => ['required', 'string'],
        'tickets.*.date' => ['required', 'date_format:Y-m-d'],
        'tickets.*.periods' => ['required', 'array'],
        'tickets.*.periods.*' => ['required', 'string'],
        'tickets.*.amount' => ['required', 'numeric', 'min:0'],
    ]);

    $saved = [];

    foreach ($payload['tickets'] as $ticket) {
        $student = $ticket['student'];
        $stationPayload = $ticket['station'];
        $periods = array_values(array_unique($ticket['periods']));

        if ($periods === []) {
            continue;
        }

        $station = Station::query()
            ->where('車別', $stationPayload['vehicle'])
            ->where('站別', $stationPayload['station'])
            ->first();

        if (! $station) {
            throw new InvalidArgumentException('找不到對應站別');
        }

        $date = Carbon\Carbon::parse($ticket['date']);
        $weekday = ['日', '一', '二', '三', '四', '五', '六'][$date->dayOfWeek];

        // Generate barcode: base36 seconds from start of year (5 chars) + student ID
        $now = Carbon\Carbon::now();
        $seconds = (int) $now->copy()->startOfYear()->diffInSeconds($now);
        $code = str_pad(
            strtoupper(base_convert((string) $seconds, 10, 36)),
            5,
            '0',
            STR_PAD_LEFT
        );
        $barcode = $code . '-' . $student['學號'];

        $record = TemporaryTicket::create([
            '學號' => (string) $student['學號'],
            '班級' => (string) $student['班級'],
            '座號' => (string) $student['座號'],
            '姓名' => (string) $student['姓名'],
            '日期' => $date->toDateString(),
            '星期' => $weekday,
            '時段' => $periods,
            'station_id' => $station->id,
            '車別' => $station->車別,
            '站別' => $station->站別,
            'barcode' => $barcode,
            '金額' => (int) $ticket['amount'],
        ]);

        $saved[] = [
            'id' => $record->id,
            '學號' => $record->學號,
            '班級' => $record->班級,
            '座號' => $record->座號,
            '姓名' => $record->姓名,
            '日期' => $record->日期->format('Y-m-d'),
            '星期' => $record->星期,
            '時段' => $record->時段,
            '車別' => $record->車別,
            '站別' => $record->站別,
            'barcode' => $record->barcode,
            '金額' => $record->金額,
        ];
    }

    return response()->json([
        'saved' => count($saved),
        'tickets' => $saved,
    ], 201);
});

Route::put('/temporary-ticket-controls/{vehicle}', function (
    string $vehicle
) {
    $data = request()->validate([
        '上學管制' => ['required', 'boolean'],
        '放學管制' => ['required', 'boolean'],
    ]);

    $control = TemporaryTicketControl::updateOrCreate(
        [
            '車別' => $vehicle,
        ],
        [
            '上學管制' => $data['上學管制'],
            '放學管制' => $data['放學管制'],
        ],
    );

    return response()->json([
        'vehicle' => $control->車別,
        '上學管制' => (bool) $control->上學管制,
        '放學管制' => (bool) $control->放學管制,
    ]);
});
