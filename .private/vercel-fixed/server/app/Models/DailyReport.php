<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    protected $table = 'reports';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'shipment_snapshot' => 'array', 'revision' => 'integer', 'last_event_id' => 'integer'];
    }
}
