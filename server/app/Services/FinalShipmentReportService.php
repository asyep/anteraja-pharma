<?php

namespace App\Services;

use App\Models\FinalShipmentReport;
use App\Models\Shipment;
use Illuminate\Support\Str;

class FinalShipmentReportService
{
    public function publish(Shipment $shipment): FinalShipmentReport
    {
        $shipment->load([
            'inspections', 'approvals', 'assignments', 'issues.decisions',
            'events', 'trackingPoints', 'aiEvaluations',
        ]);

        $snapshot = [
            'tracking_number' => $shipment->tracking_number,
            'sender_name' => $shipment->sender_name,
            'recipient_name' => $shipment->recipient_name,
            'origin_city' => $shipment->origin_city,
            'destination_city' => $shipment->destination_city,
            'item_summary' => $shipment->item_summary,
            'package_count' => $shipment->package_count,
            'temperature_profile' => $shipment->temperature_profile,
            'temperature_min' => $shipment->temperature_min,
            'temperature_max' => $shipment->temperature_max,
            'inspections' => $shipment->inspections->map(function ($item) use ($shipment) {
                return $item->only([
                    'id', 'packaging', 'seal', 'label', 'passed', 'note', 'temperature', 'created_at',
                ]) + [
                    'photo_proof_url' => $item->photo_proof_path
                        ? "/api/shipments/{$shipment->id}/inspections/{$item->id}/photo"
                        : null,
                ];
            })->all(),
            'approvals' => $shipment->approvals->map(fn ($item) => $item->only([
                'decision', 'reason', 'created_at',
            ]))->all(),
            'assignments' => $shipment->assignments->map(fn ($item) => $item->only([
                'status', 'reason', 'created_at', 'responded_at',
            ]))->all(),
            'issues' => $shipment->issues->map(fn ($item) => $item->only([
                'type', 'description', 'status', 'created_at',
            ]))->all(),
            'tracking_points' => $shipment->trackingPoints->map(fn ($item) => $item->only([
                'id', 'latitude', 'longitude', 'speed_kmh', 'temperature', 'recorded_at',
            ]))->all(),
            'ai_evaluations' => $shipment->aiEvaluations->map(fn ($item) => $item->only([
                'id', 'evaluation_id', 'condition_result', 'final_ai_status', 'incident_type', 'anomaly_details', 'evaluation_time',
            ]))->all(),
            'events' => $shipment->events->map(fn ($item) => $item->only([
                'actor_name', 'type', 'title', 'reason', 'created_at',
            ]))->all(),
        ];

        return FinalShipmentReport::firstOrCreate(
            ['shipment_id' => $shipment->id],
            [
                'report_id' => (string) Str::uuid(),
                'final_status' => $shipment->status === 'delivered'
                    && ! $shipment->aiEvaluations()->where('condition_result', false)->exists()
                    ? 'COMPLETED_SAFE'
                    : 'COMPLETED_WITH_DEVIATION',
                'delivered_to' => ['ADMIN', 'KURIR', 'VENDOR_CUSTOMER'],
                'snapshot' => $snapshot,
                'completed_at' => $shipment->completed_at ?? now(),
            ],
        );
    }
}
