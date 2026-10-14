<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['sender_chat_id', 'pod_proof_path', 'receiver_signature'];

    protected $attributes = ['version' => 1];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'completed_at' => 'datetime', 'closed_at' => 'datetime', 'is_demo' => 'boolean', 'recipient_ready' => 'boolean', 'version' => 'integer'];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AuditEvent::class)->orderBy('id');
    }

    public function trackingPoints(): HasMany
    {
        return $this->hasMany(TrackingPoint::class)->orderBy('id');
    }

    public function aiEvaluations(): HasMany
    {
        return $this->hasMany(AiEvaluation::class)->orderBy('id');
    }

    public function finalReport(): HasOne
    {
        return $this->hasOne(FinalShipmentReport::class);
    }
}
