<?php

use App\Models\Car;

it('returns the vehicle management data', function () {
    Car::create([
        '車別' => '嘉義A1',
        '搭車人數上限' => 50,
        '上學管制' => false,
        '放學管制' => false,
        '上學搭車人數' => 27,
        '放學搭車人數' => 18,
        '已繳款人數' => 20,
        '未繳款人數' => 7,
    ]);

    $response = $this->getJson('/api/vehicle-management');

    $response
        ->assertOk()
        ->assertJsonPath('0.車別', '嘉義A1')
        ->assertJsonPath('0.搭車人數上限', 50)
        ->assertJsonPath('0.上學搭車人數', 27)
        ->assertJsonPath('0.放學搭車人數', 18)
        ->assertJsonPath('0.已繳款人數', 20)
        ->assertJsonPath('0.未繳款人數', 7);
});

it('returns vehicles ordered by id', function () {
    Car::create([
        '車別' => '嘉義B2',
        '搭車人數上限' => 45,
        '上學管制' => false,
        '放學管制' => false,
        '上學搭車人數' => 16,
        '放學搭車人數' => 14,
        '已繳款人數' => 15,
        '未繳款人數' => 1,
    ]);

    Car::create([
        '車別' => '嘉義A1',
        '搭車人數上限' => 50,
        '上學管制' => false,
        '放學管制' => false,
        '上學搭車人數' => 27,
        '放學搭車人數' => 18,
        '已繳款人數' => 20,
        '未繳款人數' => 7,
    ]);

    $response = $this->getJson('/api/vehicle-management');

    $response
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.車別', '嘉義B2')
        ->assertJsonPath('1.車別', '嘉義A1');
});

it('updates a vehicle when the admin changes it', function () {
    $car = Car::create([
        '車別' => '嘉義A1',
        '搭車人數上限' => 50,
        '上學管制' => false,
        '放學管制' => false,
        '上學搭車人數' => 27,
        '放學搭車人數' => 18,
        '已繳款人數' => 20,
        '未繳款人數' => 7,
    ]);

    $response = $this->putJson('/api/vehicle-management/嘉義A1', [
        '搭車人數上限' => 55,
        '上學管制' => true,
        '放學管制' => false,
        '上學搭車人數' => 30,
        '放學搭車人數' => 22,
        '已繳款人數' => 25,
        '未繳款人數' => 5,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('車別', '嘉義A1')
        ->assertJsonPath('搭車人數上限', 55)
        ->assertJsonPath('上學管制', true)
        ->assertJsonPath('放學管制', false)
        ->assertJsonPath('上學搭車人數', 30)
        ->assertJsonPath('放學搭車人數', 22)
        ->assertJsonPath('已繳款人數', 25)
        ->assertJsonPath('未繳款人數', 5);

    $this->assertDatabaseHas('cars', [
        '車別' => '嘉義A1',
        '搭車人數上限' => 55,
        '上學管制' => 1,
        '放學管制' => 0,
        '上學搭車人數' => 30,
        '放學搭車人數' => 22,
        '已繳款人數' => 25,
        '未繳款人數' => 5,
    ]);
});

it('imports a batch of vehicle counts', function () {
    Car::create([
        '車別' => '嘉義A1',
        '搭車人數上限' => 50,
        '上學管制' => false,
        '放學管制' => false,
        '上學搭車人數' => 27,
        '放學搭車人數' => 18,
        '已繳款人數' => 20,
        '未繳款人數' => 7,
    ]);

    $response = $this->postJson('/api/vehicle-management/import', [
        'rows' => [
            [
                '車別' => '嘉義A1',
                '人數上限' => 55,
                '上學管制' => true,
                '放學管制' => false,
                '上學人數' => 30,
                '放學人數' => 22,
            ],
            [
                '車別' => '嘉義B2',
                '人數上限' => 45,
                '上學管制' => false,
                '放學管制' => true,
                '上學人數' => 14,
                '放學人數' => 14,
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('updated.0.vehicle', '嘉義A1')
        ->assertJsonPath('updated.0.搭車人數上限', 55)
        ->assertJsonPath('updated.0.上學管制', true)
        ->assertJsonPath('updated.0.放學管制', false)
        ->assertJsonPath('updated.0.上學搭車人數', 30)
        ->assertJsonPath('updated.0.放學搭車人數', 22);

    $this->assertDatabaseHas('cars', [
        '車別' => '嘉義A1',
        '搭車人數上限' => 55,
        '上學管制' => 1,
        '放學管制' => 0,
        '上學搭車人數' => 30,
        '放學搭車人數' => 22,
    ]);

    $this->assertDatabaseHas('cars', [
        '車別' => '嘉義B2',
        '搭車人數上限' => 45,
        '上學管制' => 0,
        '放學管制' => 1,
        '上學搭車人數' => 14,
        '放學搭車人數' => 14,
    ]);
});

it('creates new vehicles when importing non-existent vehicle names', function () {
    $response = $this->postJson('/api/vehicle-management/import', [
        'rows' => [
            [
                '車別' => '嘉義C3',
                '人數上限' => 40,
                '上學管制' => true,
                '放學管制' => true,
                '上學人數' => 20,
                '放學人數' => 15,
            ],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('updated.0.vehicle', '嘉義C3')
        ->assertJsonPath('updated.0.搭車人數上限', 40)
        ->assertJsonPath('updated.0.上學管制', true)
        ->assertJsonPath('updated.0.放學管制', true)
        ->assertJsonPath('updated.0.上學搭車人數', 20)
        ->assertJsonPath('updated.0.放學搭車人數', 15);

    $this->assertDatabaseHas('cars', [
        '車別' => '嘉義C3',
        '搭車人數上限' => 40,
        '上學管制' => 1,
        '放學管制' => 1,
        '上學搭車人數' => 20,
        '放學搭車人數' => 15,
        '已繳款人數' => 0,
        '未繳款人數' => 0,
    ]);
});
