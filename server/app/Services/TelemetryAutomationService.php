<?php

namespace App\Services;

use App\Models\AiEvaluation;
use App\Models\Shipment;
use App\Models\TrackingPoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TelemetryAutomationService
{
    public function record(Shipment $shipment, User $courier, array $data): AiEvaluation
    {
        $point = DB::transaction(function () use ($shipment, $courier, $data) {
            $locked = Shipment::lockForUpdate()->findOrFail($shipment->id);
            $assignment = $locked->assignments()->latest('id')->first();
            if ($courier->role !== 'courier' || ! $assignment || $assignment->courier_id !== $courier->id
                || $assignment->status !== 'accepted' || $locked->status !== 'in_transit') {
                throw ValidationException::withMessages([
                    'shipment' => 'Hanya kurir yang menerima tugas dapat mengirim telemetry saat perjalanan aktif.',
                ]);
            }

            if ($locked->temperature_profile === 'controlled' && ! isset($data['temperature'])) {
                throw ValidationException::withMessages([
                    'temperature' => 'Suhu wajib dicatat untuk pengiriman dengan rentang suhu khusus.',
                ]);
            }

            return $locked->trackingPoints()->create([
                'courier_id' => $courier->id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'speed_kmh' => $data['speed_kmh'] ?? null,
                'temperature' => $data['temperature'] ?? null,
                'recorded_at' => now(),
            ]);
        }, 3);

        $shipment->refresh();
        $shipment->update(['last_location' => sprintf('%.5f, %.5f', $point->latitude, $point->longitude)]);
        [$incidentType, $details] = $this->detectTemperatureExcursion($shipment, $point);
        $previous = $shipment->aiEvaluations()->latest('id')->first();
        $newIncident = $incidentType !== null && $previous?->incident_type !== $incidentType;
        $analysisSource = 'rules';
        $analysisWarning = null;

        if ($incidentType) {
            if ($newIncident) {
                [$summary, $analysisWarning] = $this->summarizeIncident($shipment, $point);
            } else {
                $summary = $previous?->anomaly_details['summary']
                    ?? 'Penyimpangan suhu yang sama masih berlangsung.';
            }
            $details['summary'] = $summary;
            $analysisSource = $newIncident && ! $analysisWarning && config('pharma.openai_api_key')
                ? 'openai'
                : 'rules';
            if ($analysisWarning) {
                $details['ai_assistance'] = 'unavailable';
            }
        }

        return DB::transaction(function () use ($shipment, $point, $incidentType, $details, $analysisSource, $analysisWarning, $newIncident, $previous) {
            $evaluation = AiEvaluation::create([
                'evaluation_id' => (string) \Illuminate\Support\Str::uuid(),
                'shipment_id' => $shipment->id,
                'tracking_point_id' => $point->id,
                'condition_result' => $incidentType === null,
                'final_ai_status' => $incidentType ? 'Penyimpangan' : 'aman',
                'incident_type' => $incidentType,
                'anomaly_details' => $details ? $details + [
                    'analysis_source' => $analysisSource,
                    'analysis_warning' => $analysisWarning,
                ] : null,
                'evaluation_time' => now(),
            ]);

            if ($newIncident) {
                app(WorkflowService::class)->event(
                    $shipment,
                    null,
                    'ai_deviation',
                    'Penyimpangan suhu terdeteksi',
                    $details['summary'] ?? 'Suhu paket berada di luar rentang yang diizinkan selama lebih dari lima menit.',
                    $shipment->status,
                    ['evaluation_id' => $evaluation->evaluation_id, 'incident_type' => $incidentType],
                );
            } elseif ($previous?->incident_type === 'SIGNAL_LOSS' && $incidentType === null) {
                app(WorkflowService::class)->event(
                    $shipment,
                    null,
                    'ai_recovered',
                    'Telemetry kembali diterima',
                    'Sinyal GPS kembali tersedia dan pembaruan kurir berhasil diterima.',
                    $shipment->status,
                );
            }

            return $evaluation;
        }, 3);
    }

    public function detectTelemetryDisruptions(): int
    {
        $count = 0;
        foreach (Shipment::where('status', 'in_transit')->pluck('id') as $id) {
            $count += DB::transaction(function () use ($id) {
                $shipment = Shipment::lockForUpdate()->find($id);
                if (! $shipment || $shipment->status !== 'in_transit') {
                    return 0;
                }
                $departed = $shipment->events()->where('type', 'depart')->latest('id')->first();
                $lastPoint = $shipment->trackingPoints()->latest('recorded_at')->first();
                $lastSeen = $lastPoint?->recorded_at ?? $departed?->created_at;
                if (! $lastSeen || $lastSeen->gt(now()->subMinutes(15))) {
                    return 0;
                }
                $previous = $shipment->aiEvaluations()->latest('id')->first();
                if ($previous?->incident_type === 'SIGNAL_LOSS' && ! $previous->condition_result) {
                    return 0;
                }

                $evaluation = AiEvaluation::create([
                    'evaluation_id' => (string) \Illuminate\Support\Str::uuid(),
                    'shipment_id' => $shipment->id,
                    'tracking_point_id' => null,
                    'condition_result' => false,
                    'final_ai_status' => 'Penyimpangan',
                    'incident_type' => 'SIGNAL_LOSS',
                    'anomaly_details' => [
                        'summary' => 'Tidak ada telemetry GPS selama lebih dari 15 menit.',
                        'last_seen_at' => $lastSeen->toISOString(),
                        'analysis_source' => 'rules',
                    ],
                    'evaluation_time' => now(),
                ]);
                app(WorkflowService::class)->event(
                    $shipment,
                    null,
                    'ai_deviation',
                    'Telemetry GPS terputus',
                    'Tidak ada telemetry GPS selama lebih dari 15 menit.',
                    $shipment->status,
                    ['evaluation_id' => $evaluation->evaluation_id, 'incident_type' => 'SIGNAL_LOSS'],
                );

                return 1;
            }, 3);
        }

        return $count;
    }

    private function detectTemperatureExcursion(Shipment $shipment, TrackingPoint $point): array
    {
        if ($shipment->temperature_profile !== 'controlled' || $point->temperature === null) {
            return [null, null];
        }

        $outsideRange = fn (TrackingPoint $sample) => $sample->temperature < $shipment->temperature_min
            || $sample->temperature > $shipment->temperature_max;
        if (! $outsideRange($point)) {
            return [null, null];
        }

        $now = now();
        $samples = $shipment->trackingPoints()
            ->where('recorded_at', '>=', $now->copy()->subMinutes(20))
            ->reorder('recorded_at', 'desc')
            ->get();
        $excursion = collect();
        foreach ($samples as $sample) {
            if (! $outsideRange($sample) || ($excursion->isNotEmpty()
                && $excursion->last()->recorded_at->diffInSeconds($sample->recorded_at) > 180)) {
                break;
            }
            $excursion->push($sample);
        }
        $cutoff = $now->copy()->subMinutes(5);
        if ($excursion->count() < 2 || $excursion->last()->recorded_at->gt($cutoff)) {
            return [null, null];
        }

        return ['TEMP_EXCURSION', [
            'temperature' => $point->temperature,
            'allowed_min' => (float) $shipment->temperature_min,
            'allowed_max' => (float) $shipment->temperature_max,
            'latitude' => $point->latitude,
            'longitude' => $point->longitude,
            'duration_minutes' => 5,
        ]];
    }

    private function summarizeIncident(Shipment $shipment, TrackingPoint $point): array
    {
        $fallback = sprintf(
            'Suhu %.1f°C di luar rentang %.1f–%.1f°C selama setidaknya lima menit pada koordinat %.5f, %.5f.',
            $point->temperature,
            $shipment->temperature_min,
            $shipment->temperature_max,
            $point->latitude,
            $point->longitude,
        );
        $apiKey = config('pharma.openai_api_key');
        if (! $apiKey) {
            return [$fallback, null];
        }

        try {
            $response = Http::connectTimeout(2)->timeout(4)->withToken($apiKey)->post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model' => config('pharma.openai_model'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'Buat ringkasan insiden logistik farmasi dalam Bahasa Indonesia. Jangan mengubah fakta atau memberi diagnosis. Maksimal 35 kata.'],
                        ['role' => 'user', 'content' => json_encode([
                            'shipment_id' => $shipment->id,
                            'incident' => 'TEMP_EXCURSION',
                            'temperature_celsius' => $point->temperature,
                            'allowed_min_celsius' => (float) $shipment->temperature_min,
                            'allowed_max_celsius' => (float) $shipment->temperature_max,
                            'latitude' => $point->latitude,
                            'longitude' => $point->longitude,
                            'duration_minutes' => 5,
                        ], JSON_THROW_ON_ERROR)],
                    ],
                    'max_tokens' => 100,
                    'temperature' => 0,
                ],
            );
            if (! $response->successful()) {
                Log::warning('pharma.openai.request_failed', ['status' => $response->status()]);

                return [$fallback, 'OpenAI tidak tersedia; ringkasan menggunakan aturan keselamatan lokal.'];
            }
            $summary = trim((string) $response->json('choices.0.message.content'));
            if ($summary === '') {
                Log::warning('pharma.openai.empty_summary');

                return [$fallback, 'OpenAI mengembalikan ringkasan kosong; ringkasan menggunakan aturan keselamatan lokal.'];
            }

            return [$summary, null];
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            Log::warning('pharma.openai.connection_failed', ['exception_type' => get_class($exception)]);

            return [$fallback, 'Koneksi OpenAI gagal; ringkasan menggunakan aturan keselamatan lokal.'];
        }
    }
}
