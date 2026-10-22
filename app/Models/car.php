<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Car extends Model
{
    protected $fillable = [
        '車別',
        '搭車人數上限',
        '上學管制',
        '放學管制',
        '上學搭車人數',
        '放學搭車人數',
        '已繳款人數',
        '未繳款人數',
    ];

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }
}
