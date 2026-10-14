<?php

namespace App\Services;

use App\Models\FinalShipmentReport;
use App\Models\Shipment;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class FinalShipmentReportService
{
    public function publish(Shipment $shipment): FinalShipmentReport
    {
        $shipment->load([
            'inspections.user', 'approvals', 'assignments', 'issues.decisions',
            'events', 'trackingPoints', 'aiEvaluations',
        ]);

        $snapshot = [
            'tracking_number' => $shipment->tracking_number,
            'is_demo' => (bool)$shipment->is_demo,
            'weight_kg' => $shipment->weight_kg,
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
                    'checker_name' => $item->user?->name ?? 'Checker',
                    'proof_path' => $item->photo_proof_path,
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
            ]) + ['decisions' => $item->decisions->map(fn ($decision) => $decision->only(['action', 'reason', 'created_at']))->all()])->all(),
            'tracking_points' => $shipment->trackingPoints->map(fn ($item) => $item->only([
                'id', 'latitude', 'longitude', 'speed_kmh', 'temperature', 'recorded_at', 'simulated',
            ]))->all(),
            'ai_evaluations' => $shipment->aiEvaluations->map(fn ($item) => $item->only([
                'id', 'evaluation_id', 'condition_result', 'final_ai_status', 'incident_type', 'anomaly_details', 'evaluation_time',
            ]))->all(),
            'events' => $shipment->events->map(fn ($item) => $item->only([
                'actor_name', 'type', 'title', 'reason', 'created_at',
            ]))->all(),
            'temperature_summary' => ['min' => $shipment->trackingPoints->whereNotNull('temperature')->min('temperature'), 'max' => $shipment->trackingPoints->whereNotNull('temperature')->max('temperature')],
            'pod' => ['proof_path' => $shipment->pod_proof_path, 'received_by' => $shipment->received_by, 'receiver_signature' => $shipment->receiver_signature],
        ];

        $report = FinalShipmentReport::firstOrCreate(
            ['shipment_id' => $shipment->id],
            [
                'report_id' => (string) Str::uuid(),
                'final_status' => $shipment->status === 'delivered'
                    && ! $shipment->aiEvaluations()->where('condition_result', false)->exists() && !$shipment->issues()->exists()
                    ? 'COMPLETED_SAFE'
                    : 'COMPLETED_WITH_DEVIATION',
                'delivered_to' => ['ADMIN', 'KURIR', 'VENDOR_CUSTOMER'],
                'snapshot' => $snapshot,
                'verification_token' => bin2hex(random_bytes(32)),
                'completed_at' => $shipment->completed_at ?? now(),
            ],
        );
        if ($report->wasRecentlyCreated) {
            $bytes = app(ShipmentPdfService::class)->render($report);
            $report->update(['pdf_content' => Crypt::encryptString(base64_encode($bytes)), 'sha256' => hash('sha256', $bytes)]);
            $courierId = $shipment->assignments()->latest('id')->value('courier_id');
            $recipients = User::where('role', 'admin')->orWhereIn('id', array_filter([$shipment->buyer_id, $courierId]))->get();
            foreach ($recipients as $u) {
                OutboundMessage::firstOrCreate(['dedupe_key' => 'final:'.$report->report_id.':'.$u->id], [
                    'shipment_id' => $shipment->id, 'user_id' => $u->id, 'audience' => $u->role, 'chat_id' => $u->telegram_chat_id,
                    'subject' => 'Laporan akhir '.$shipment->tracking_number,
                    'body' => 'Laporan akhir '.$shipment->tracking_number.' tersedia. Masuk dengan akunmu untuk melihat bukti, riwayat dan keputusan.',
                    'action_url' => rtrim(config('pharma.frontend_url'), '/').'/kiriman/'.$shipment->id,
                    'status' => config('pharma.demo_seed') ? 'demo' : 'pending',
                ]);
            }
        }
        return $report;
    }
}
