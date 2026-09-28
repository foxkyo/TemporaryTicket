<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemporaryTicketControl extends Model
{
    protected $fillable = [
        '車別',
        '上學管制',
        '放學管制',
    ];

    protected function casts(): array
    {
        return [
            '上學管制' => 'boolean',
            '放學管制' => 'boolean',
        ];
    }
}