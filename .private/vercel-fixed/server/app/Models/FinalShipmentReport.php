<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalShipmentReport extends Model
{
    protected $table = 'shipment_reports';

    protected $guarded = ['id'];
    protected $hidden = ['pdf_content', 'verification_token'];

    protected function casts(): array
    {
        return [
            'delivered_to' => 'array',
            'snapshot' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
