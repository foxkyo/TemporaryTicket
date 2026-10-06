<?php

use App\Models\Station;

it('returns the vehicle initial counts with station totals as the fallback', function () {
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

    $response = $this->getJson('/api/vehicle-initial-counts');

    $response
        ->assertOk()
        ->assertJsonPath('0.vehicle', '嘉義A1')
        ->assertJsonPath('0.上學人數', 27)
        ->assertJsonPath('0.放學人數', 18);
});

it('returns vehicle totals ordered by the earliest station id', function () {
    Station::create([
        '車別' => '嘉義B2',
        '站別' => '北門站',
        '乘車處' => '北門路',
        '單趟金額' => 110,
        '單月金額' => 330,
        '上學人數' => 9,
        '放學人數' => 7,
        '已繳款人數' => 3,
        '未繳款人數' => 1,
    ]);

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

    $response = $this->getJson('/api/vehicle-initial-counts');

    $response
        ->assertOk()
        ->assertJsonPath('0.vehicle', '嘉義B2')
        ->assertJsonPath('1.vehicle', '嘉義A1');
});

it('updates the same vehicle totals in the stations table when the admin changes a vehicle', function () {
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

    $response = $this->putJson('/api/vehicle-initial-counts/嘉義A1', [
        '上學人數' => 30,
        '放學人數' => 22,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('vehicle', '嘉義A1')
        ->assertJsonPath('上學人數', 30)
        ->assertJsonPath('放學人數', 22);

    $this->assertDatabaseHas('stations', [
        '車別' => '嘉義A1',
        '站別' => '中山站',
        '上學人數' => 15,
        '放學人數' => 11,
    ]);

    $this->assertDatabaseHas('stations', [
        '車別' => '嘉義A1',
        '站別' => '民權站',
        '上學人數' => 15,
        '放學人數' => 11,
    ]);
});

it('imports a batch of vehicle counts into the stations entries for the matching vehicle', function () {
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

    $response = $this->postJson('/api/vehicle-initial-counts/import', [
        'rows' => [
            ['車別' => '嘉義A1', '上學人數' => 25, '放學人數' => 18],
            ['車別' => '嘉義B2', '人數' => 14],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('updated.0.vehicle', '嘉義A1')
        ->assertJsonPath('updated.0.上學人數', 25)
        ->assertJsonPath('updated.0.放學人數', 18);

    $this->assertDatabaseHas('stations', [
        '車別' => '嘉義A1',
        '站別' => '中山站',
        '上學人數' => 13,
        '放學人數' => 9,
    ]);

    $this->assertDatabaseHas('stations', [
        '車別' => '嘉義A1',
        '站別' => '民權站',
        '上學人數' => 12,
        '放學人數' => 9,
    ]);
});
