<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    protected $table = 'stations';

    protected $fillable = [
        '車別',
        '站別',
        '乘車處',
        '單趟金額',
        '單月金額',
    ];

    public $timestamps = false;

    public function car()
    {
        return $this->belongsTo(Car::class, '車別', '車別');
    }
}
