<?php

namespace App\Services;

use App\Models\AiEvaluation;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TelemetryAutomationService
{
    public function record(Shipment $shipment, User $courier, array $data, bool $simulated = false): AiEvaluation
    {
        // sample, evaluation, hold, audit and outbox commit together.
        return DB::transaction(function () use ($shipment, $courier, $data, $simulated) {
            $s = Shipment::lockForUpdate()->findOrFail($shipment->id);
            $assignment = $s->assignments()->latest('id')->first();
            abort_unless($courier->role === 'courier' && $assignment && $assignment->courier_id === $courier->id && $assignment->status === 'accepted', 403);
            if (!empty($data['client_event_id'])) {
                $point = $s->trackingPoints()->where('client_event_id', $data['client_event_id'])->first();
                if ($point) return AiEvaluation::where('tracking_point_id', $point->id)->firstOrFail();
            }
            if ($s->closed_at || !in_array($s->status, ['in_transit', 'held'])) {
                throw ValidationException::withMessages(['shipment' => 'Telemetry hanya diterima selama perjalanan atau penanganan kendala aktif.']);
            }
            if ($s->temperature_profile === 'controlled' && !isset($data['temperature'])) {
                throw ValidationException::withMessages(['temperature' => 'Catat suhu terukur untuk kiriman dengan rentang khusus.']);
            }
            $recorded = isset($data['recorded_at']) ? Carbon::parse($data['recorded_at'])->utc() : now();
            $point = $s->trackingPoints()->create([
                'courier_id' => $courier->id, 'latitude' => $data['latitude'], 'longitude' => $data['longitude'],
                'speed_kmh' => $data['speed_kmh'] ?? null, 'temperature' => $data['temperature'] ?? null,
                'recorded_at' => $recorded, 'client_event_id' => $data['client_event_id'] ?? null, 'simulated' => $simulated,
            ]);
            $latest = $s->trackingPoints()->reorder('recorded_at', 'desc')->first();
            if ($latest->id === $point->id) $s->last_location = sprintf('%.5f, %.5f', $point->latitude, $point->longitude);
            $details = ['analysis_source' => 'rules', 'simulated' => $simulated, 'summary' => 'Data terbaru berada dalam batas pemantauan.'];
            $incident = null;
            if ($s->temperature_profile === 'controlled') {
                $outside = fn ($p) => $p->temperature !== null && ($p->temperature < $s->temperature_min || $p->temperature > $s->temperature_max);
                $sampleQuery = $s->trackingPoints()->where('simulated', $simulated)->where('recorded_at', '<=', $recorded)->where('recorded_at', '>=', $recorded->copy()->subMinutes(20));
                // virtual-time scenarios never blend with real gps or an earlier scenario.
                if ($simulated && !empty($data['client_event_id'])) $sampleQuery->where('client_event_id', 'like', substr($data['client_event_id'], 0, 36).'-%');
                $samples = $sampleQuery->reorder('recorded_at', 'desc')->limit(150)->get();
                if ($outside($point)) {
                    $start = $recorded;
                    $last = $recorded;
                    foreach ($samples as $sample) {
                        if (!$outside($sample) || abs($last->diffInSeconds($sample->recorded_at)) > 180) break;
                        $start = $sample->recorded_at;
                        $last = $sample->recorded_at;
                    }
                    $duration = abs($recorded->diffInSeconds($start));
                    $details += ['temperature' => $point->temperature, 'allowed_min' => (float)$s->temperature_min, 'allowed_max' => (float)$s->temperature_max, 'duration_seconds' => $duration];
                    $details['summary'] = sprintf('Suhu %.1f°C di luar rentang %.1f–%.1f°C. Paparan tercatat %d detik.', $point->temperature, $s->temperature_min, $s->temperature_max, $duration);
                    if ($duration >= config('pharma.excursion_seconds', 180)) $incident = 'TEMP_EXCURSION';
                    else $details['warning'] = 'Suhu di luar rentang; periksa cold-box sebelum paparan bertambah.';
                } elseif ($samples->count() >= 2) {
                    $first = $samples->last();
                    $seconds = abs($first->recorded_at->diffInSeconds($point->recorded_at));
                    if ($seconds >= 10 && $first->temperature !== null) {
                        $rate = ($point->temperature - $first->temperature) / ($seconds / 60);
                        $eta = $rate > 0 ? ($s->temperature_max - $point->temperature) / $rate : null;
                        if ($eta !== null && $eta > 0 && $eta <= 5) {
                            $details['warning'] = sprintf('Estimasi suhu melewati batas dalam %.1f menit. Periksa cold-box; ini estimasi, bukan pembacaan sensor.', $eta);
                            $details['predicted_breach_minutes'] = round($eta, 1);
                        }
                    }
                }
            }
            $previous = $s->aiEvaluations()->latest('id')->first();
            $evaluation = $s->aiEvaluations()->create([
                'evaluation_id' => (string)Str::uuid(), 'tracking_point_id' => $point->id,
                'condition_result' => $incident === null, 'final_ai_status' => $incident ? 'Penyimpangan' : 'aman',
                'incident_type' => $incident, 'anomaly_details' => $details, 'evaluation_time' => now(),
            ]);
            if ($incident) $this->hold($s, 'temperature', $details['summary'], $evaluation->evaluation_id);
            if ($incident && config('pharma.openai_api_key') && (!$previous || $previous->incident_type !== $incident)) {
                \App\Jobs\SummarizeIncident::dispatch($evaluation->id)->onConnection('database')->afterCommit();
            }
            if (!$incident && $previous?->incident_type === 'SIGNAL_LOSS') {
                // restored gps is evidence; only admin may release the hold.
                app(WorkflowService::class)->event($s, null, 'ai_recovered', 'Telemetry kembali diterima', 'GPS kembali tersedia. Pengiriman tetap ditahan sampai keputusan Admin.', $s->status);
            }
            $s->save();
            return $evaluation;
        }, 3);
    }

    public function hold(Shipment $s, string $type, string $reason, string $evaluationId): void
    {
        if ($s->issues()->where('status', 'open')->where('type', $type)->exists()) return;
        $before = $s->status;
        $previousStatus = $before === 'held' ? ($s->issues()->where('status', 'open')->value('previous_status') ?? 'in_transit') : $before;
        $s->issues()->create(['user_id' => $s->created_by, 'type' => $type, 'description' => $reason, 'previous_status' => $previousStatus]);
        $s->status = 'held';
        $s->version++;
        $s->save();
        app(WorkflowService::class)->event($s, null, 'ai_deviation', 'Penyimpangan terdeteksi — pengiriman ditahan', $reason, $before, ['evaluation_id' => $evaluationId]);
    }

    public function detectTelemetryDisruptions(): int
    {
        $count = 0;
        foreach (Shipment::where('status', 'in_transit')->whereNull('closed_at')->pluck('id') as $id) {
            $count += DB::transaction(function () use ($id) {
                $s = Shipment::lockForUpdate()->find($id);
                if (!$s || $s->status !== 'in_transit') return 0;
                $last = $s->trackingPoints()->reorder('recorded_at', 'desc')->first()?->recorded_at
                    ?? $s->events()->where('type', 'depart')->latest('id')->first()?->created_at;
                if (!$last || $last->gt(now()->subMinutes(15))) return 0;
                $evaluation = $s->aiEvaluations()->create([
                    'evaluation_id' => (string)Str::uuid(), 'condition_result' => false, 'final_ai_status' => 'Penyimpangan',
                    'incident_type' => 'SIGNAL_LOSS', 'anomaly_details' => ['analysis_source' => 'rules', 'summary' => 'Telemetry terputus lebih dari 15 menit.', 'last_seen_at' => $last->toISOString()], 'evaluation_time' => now(),
                ]);
                $this->hold($s, 'signal_loss', 'Telemetry terputus lebih dari 15 menit. Hubungi kurir dan verifikasi kondisi paket.', $evaluation->evaluation_id);
                return 1;
            }, 3);
        }
        return $count;
    }
}
