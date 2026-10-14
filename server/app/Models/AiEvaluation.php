<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEvaluation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'condition_result' => 'boolean',
            'anomaly_details' => 'array',
            'evaluation_time' => 'datetime',
        ];
    }

    public function trackingPoint(): BelongsTo
    {
        return $this->belongsTo(TrackingPoint::class);
    }
}
