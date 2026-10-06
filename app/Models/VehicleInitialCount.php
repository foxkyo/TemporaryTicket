<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleInitialCount extends Model
{
    protected $table = 'vehicle_initial_counts';

    protected $fillable = [
        '車別',
        '上學人數',
        '放學人數',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            '上學人數' => 'integer',
            '放學人數' => 'integer',
        ];
    }
}
