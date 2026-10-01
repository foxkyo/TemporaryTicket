<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryTicket extends Model
{
    protected $fillable = [
        '學號',
        '班級',
        '座號',
        '姓名',
        '日期',
        '星期',
        '時段',
        'station_id',
        '車別',
        '站別',
        'barcode',
        '金額',
    ];

    protected function casts(): array
    {
        return [
            '日期' => 'date',
            '時段' => 'array',
            '金額' => 'integer',
        ];
    }


    public function set時段Attribute($value)
    {
        $this->attributes['時段'] = json_encode($value, 320);
    }

    public function get時段Attribute($value)
    {
        if ($value === null) {
            return null;
        }
        return json_decode($value, 320);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
