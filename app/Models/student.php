<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class student extends Model
{
    protected $table = 'students';

    protected $fillable = [
        '學號',
        '班級',
        '座號',
        '姓名',
    ];

    public $timestamps = false;
}