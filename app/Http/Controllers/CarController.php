<?php

namespace App\Http\Controllers;

use App\Http\Resources\CarResource;
use App\Models\Car;
use Illuminate\Http\Request;

class CarController extends Controller
{
    public function index()
    {
        $cars = Car::query()
            ->orderBy('id')
            ->get();

        return response()->json(CarResource::collection($cars)->resolve());
    }

    public function update(Request $request, string $vehicle)
    {
        $data = $request->validate([
            '搭車人數上限' => ['required', 'integer', 'min:0', 'max:99'],
            '上學管制' => ['boolean'],
            '放學管制' => ['boolean'],
            '上學搭車人數' => ['required', 'integer', 'min:0', 'max:99'],
            '放學搭車人數' => ['required', 'integer', 'min:0', 'max:99'],
            '已繳款人數' => ['nullable', 'integer', 'min:0', 'max:99'],
            '未繳款人數' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $car = Car::query()
            ->where('車別', $vehicle)
            ->first();

        if (! $car) {
            return response()->json([
                'message' => '找不到對應車別',
            ], 404);
        }

        $car->update($data);

        return response()->json(CarResource::make($car)->resolve());
    }

    public function import(Request $request)
    {
        $payload = $request->validate([
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

            $car = Car::query()
                ->where('車別', $vehicle)
                ->first();

            $capacity = $normalized['人數上限'] ?? $normalized['搭車人數上限'] ?? $normalized['capacity'] ?? 0;

            $parseBoolean = function ($value) {
                if (is_bool($value)) {
                    return $value;
                }
                $str = strtolower(trim((string) $value));

                return $str === '是' || $str === 'true' || $str === '1' || $str === 'yes';
            };

            $morningControl = $normalized['上學管制'] ?? false;
            $afternoonControl = $normalized['放學管制'] ?? false;

            $morningCount = $normalized['上學人數'] ?? $normalized['上學搭車人數'] ?? $normalized['上學'] ?? $normalized['morning'] ?? 0;
            $afternoonCount = $normalized['放學人數'] ?? $normalized['放學搭車人數'] ?? $normalized['放學'] ?? $normalized['afternoon'] ?? 0;

            if ($car) {
                $car->update([
                    '搭車人數上限' => (int) $capacity,
                    '上學管制' => $parseBoolean($morningControl),
                    '放學管制' => $parseBoolean($afternoonControl),
                    '上學搭車人數' => (int) $morningCount,
                    '放學搭車人數' => (int) $afternoonCount,
                ]);
            } else {
                $car = Car::create([
                    '車別' => $vehicle,
                    '搭車人數上限' => (int) $capacity,
                    '上學管制' => $parseBoolean($morningControl),
                    '放學管制' => $parseBoolean($afternoonControl),
                    '上學搭車人數' => (int) $morningCount,
                    '放學搭車人數' => (int) $afternoonCount,
                    '已繳款人數' => 0,
                    '未繳款人數' => 0,
                ]);
            }

            $updated[] = [
                'vehicle' => $vehicle,
                '車別' => $vehicle,
                '搭車人數上限' => (int) $capacity,
                '上學管制' => $parseBoolean($morningControl),
                '放學管制' => $parseBoolean($afternoonControl),
                '上學搭車人數' => (int) $morningCount,
                '放學搭車人數' => (int) $afternoonCount,
            ];
        }

        return response()->json([
            'updated' => $updated,
        ]);
    }
}
