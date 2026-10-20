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
        '上學人數',
        '放學人數',
        '已繳款人數',
        '未繳款人數',
    ];

    public $timestamps = false;
}