<?php

use App\Models\Station;
use App\Models\Student;
use App\Models\TemporaryTicket;

it('resolves the temporary ticket control model class', function () {
    expect(class_exists(\App\Models\TemporaryTicketControl::class))->toBeTrue();
});

it('returns grouped student data for the selector', function () {
    Student::create([
        '學號' => '001',
        '班級' => '國一1',
        '座號' => 1,
        '姓名' => '小明',
    ]);

    $response = $this->getJson('/api/students');

    $response
        ->assertOk()
        ->assertJsonFragment([
            'grade' => '國一',
        ])
        ->assertJsonPath('0.grade', '國一')
        ->assertJsonPath('0.classes.0.students.0.姓名', '小明');
});

it('returns grouped station data for the selector', function () {
    Station::create([
        '車別' => '嘉義A1',
        '站別' => '中山站',
        '乘車處' => '中山路',
        '單趟金額' => 120,
        '單月金額' => 360,
        '上學人數' => 12,
        '放學人數' => 8,
        '已繳款人數' => 4,
        '未繳款人數' => 2,
    ]);

    Station::create([
        '車別' => '嘉義A1',
        '站別' => '民權站',
        '乘車處' => '民權路',
        '單趟金額' => 140,
        '單月金額' => 420,
        '上學人數' => 15,
        '放學人數' => 10,
        '已繳款人數' => 5,
        '未繳款人數' => 2,
    ]);

    $response = $this->getJson('/api/stations');

    $response
        ->assertOk()
        ->assertJsonPath('0.area', '嘉義縣市')
        ->assertJsonPath('0.vehicles.0.vehicle', '嘉義A1')
        ->assertJsonPath('0.vehicles.0.stations.0.station', '中山站')
        ->assertJsonPath('0.vehicles.0.stations.0.one_way_amount', 120)
        ->assertJsonPath('0.vehicles.0.上學.total', 27)
        ->assertJsonPath('0.vehicles.0.放學.total', 18);
});

it('returns the latest ticket record for the selected student so the form can prefill period and station', function () {
    $station = Station::create([
        '車別' => '嘉義A1',
        '站別' => '中山站',
        '乘車處' => '中山路',
        '單趟金額' => 120,
        '單月金額' => 360,
        '上學人數' => 12,
        '放學人數' => 8,
        '已繳款人數' => 4,
        '未繳款人數' => 2,
    ]);

    TemporaryTicket::create([
        '學號' => '001',
        '班級' => '國一1',
        '座號' => '1',
        '姓名' => '小明',
        '日期' => '2026-09-30',
        '星期' => '三',
        '時段' => ['上學'],
        'station_id' => $station->id,
        '車別' => '嘉義A1',
        '站別' => '中山站',
        'barcode' => 'old-ticket',
        '金額' => 120,
    ]);

    TemporaryTicket::create([
        '學號' => '001',
        '班級' => '國一1',
        '座號' => '1',
        '姓名' => '小明',
        '日期' => '2026-10-01',
        '星期' => '四',
        '時段' => ['放學'],
        'station_id' => $station->id,
        '車別' => '嘉義A1',
        '站別' => '中山站',
        'barcode' => 'latest-ticket',
        '金額' => 120,
    ]);

    $response = $this->getJson('/api/temporary-tickets/latest-for-student?學號=001&班級=國一1&姓名=小明');

    $response
        ->assertOk()
        ->assertJsonPath('ticket.時段.0', '放學')
        ->assertJsonPath('ticket.車別', '嘉義A1')
        ->assertJsonPath('ticket.站別', '中山站');
});

it('stores valid temporary ticket rows when the user confirms', function () {
    $station = Station::create([
        '車別' => '嘉義A1',
        '站別' => '中山站',
        '乘車處' => '中山路',
        '單趟金額' => 120,
        '單月金額' => 360,
        '上學人數' => 12,
        '放學人數' => 8,
        '已繳款人數' => 4,
        '未繳款人數' => 2,
    ]);

    Student::create([
        '學號' => '001',
        '班級' => '國一1',
        '座號' => 1,
        '姓名' => '小明',
    ]);

    $response = $this->postJson('/api/temporary-tickets', [
        'tickets' => [[
            'student' => [
                '學號' => '001',
                '班級' => '國一1',
                '座號' => 1,
                '姓名' => '小明',
            ],
            'station' => [
                'id' => $station->id,
                'vehicle' => '嘉義A1',
                'station' => '中山站',
                'one_way_amount' => 120,
            ],
            'date' => '2026-10-01',
            'periods' => ['放學'],
            'amount' => 120,
        ]],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('saved', 1)
        ->assertJsonPath('tickets.0.姓名', '小明');

    $this->assertDatabaseHas('temporary_tickets', [
        '學號' => '001',
        '班級' => '國一1',
        '座號' => '1',
        '姓名' => '小明',
        '日期' => '2026-10-01 00:00:00',
        '星期' => '四',
        '車別' => '嘉義A1',
        '站別' => '中山站',
        '金額' => 120,
    ]);
});
