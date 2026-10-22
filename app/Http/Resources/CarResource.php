<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle' => $this->車別,
            '車別' => $this->車別,
            '搭車人數上限' => (int) $this->搭車人數上限,
            '上學管制' => (bool) $this->上學管制,
            '放學管制' => (bool) $this->放學管制,
            '上學搭車人數' => (int) $this->上學搭車人數,
            '放學搭車人數' => (int) $this->放學搭車人數,
            '已繳款人數' => (int) $this->已繳款人數,
            '未繳款人數' => (int) $this->未繳款人數,
        ];
    }
}
