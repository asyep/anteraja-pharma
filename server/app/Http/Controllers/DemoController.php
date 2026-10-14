<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\User;
use App\Models\OutboundMessage;
use App\Services\TelemetryAutomationService;
use App\Services\WorkflowService;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoController extends Controller
{
    private function guard(Request $r): void
    {
        abort_unless($r->user()->role === 'admin' && config('pharma.demo_enabled') && in_array(app()->environment(), ['local', 'testing', 'demo', 'development']), 403);
    }

    public function index(Request $r)
    {
        $this->guard($r);
        return Shipment::where('is_demo', true)->whereNull('closed_at')->whereIn('status', ['in_transit', 'held'])->latest('id')->limit(100)->get(['id', 'tracking_number', 'status', 'temperature_min', 'temperature_max', 'temperature_profile']);
    }

    public function run(Request $r, int $id, TelemetryAutomationService $engine)
    {
        $this->guard($r);
        $data = $r->validate(['scenario' => 'required|in:normal,spike,trend,signal_loss,seal,route', 'temperature' => 'sometimes|numeric|between:-10,40', 'speed' => 'sometimes|in:1,10,60', 'event_id' => 'required|uuid']);
        return DB::transaction(function () use ($id, $data, $r, $engine) {
            $s = Shipment::lockForUpdate()->findOrFail($id);
            abort_unless($s->is_demo && !$s->closed_at && in_array($s->status, ['in_transit', 'held']), 422, 'Pilih kiriman demonstrasi yang sedang berjalan.');
            $receipt = $s->events()->where('type', 'demo_trigger')->where('metadata->event_id', $data['event_id'])->first();
            if ($receipt) return ['message' => 'Skenario ini sudah diproses.', 'status' => $s->status];
            $assignment = $s->assignments()->latest('id')->first();
            abort_unless($assignment && $assignment->status === 'accepted', 422);
            $courier = User::findOrFail($assignment->courier_id);
            app(WorkflowService::class)->event($s, $r->user(), 'demo_trigger', 'Simulasi: '.$data['scenario'], 'Data demonstrasi, bukan kejadian atau sensor nyata.', $s->status, ['event_id' => $data['event_id'], 'scenario' => $data['scenario'], 'speed' => $data['speed'] ?? 1]);
            if ($data['scenario'] === 'seal') {
                abort_unless($s->status === 'in_transit', 422, 'Tangani kendala yang masih terbuka dulu.');
                app(WorkflowService::class)->apply($id, $courier, 'issue', ['version' => $s->version, 'reason' => 'Simulasi Admin: segel paket ditemukan terbuka.', 'issue_type' => 'seal']);
            } elseif ($data['scenario'] === 'signal_loss') {
                $evaluation = $s->aiEvaluations()->create(['evaluation_id' => (string)Str::uuid(), 'condition_result' => false, 'final_ai_status' => 'Penyimpangan', 'incident_type' => 'SIGNAL_LOSS', 'anomaly_details' => ['analysis_source' => 'rules', 'simulated' => true, 'virtual_elapsed_seconds' => 901, 'summary' => 'Simulasi jam demo: telemetry terputus 15 menit.'], 'evaluation_time' => now()]);
                $engine->hold($s, 'signal_loss', 'Simulasi jam demo: telemetry terputus lebih dari 15 menit.', $evaluation->evaluation_id);
            } else {
                $count = match ($data['scenario']) { 'spike' => 4, 'trend' => 3, 'route' => 10, default => 1 };
                for ($i = 0; $i < $count; $i++) {
                    $temp = $data['temperature'] ?? ($s->temperature_profile === 'controlled' ? ((float)$s->temperature_min + (float)$s->temperature_max) / 2 : 22);
                    if ($data['scenario'] === 'spike') {
                        abort_unless($s->temperature_profile === 'controlled', 422, 'Lonjakan suhu memerlukan kiriman dengan rentang suhu khusus.');
                        $temp = $data['temperature'] ?? 12.5;
                    }
                    if ($data['scenario'] === 'trend') $temp = (float)($s->temperature_max ?? 25) - 1.5 + $i * 0.5;
                    $engine->record($s, $courier, ['latitude' => -6.2088 - $i * 0.002, 'longitude' => 106.8456 + $i * 0.002, 'speed_kmh' => 24,
                        'temperature' => $temp, 'recorded_at' => now()->subSeconds(($count - 1 - $i) * 60 / ($data['scenario'] === 'route' ? (int)($data['speed'] ?? 1) : 1))->toISOString(), 'client_event_id' => $data['event_id'].'-'.$i], true);
                }
            }
            return ['message' => 'Skenario demonstrasi tercatat. Lihat monitor dan riwayat kiriman.', 'status' => $s->fresh()->status];
        }, 3);
    }

    public function message(Request $r, MessageService $service)
    {
        $this->guard($r);
        $d = $r->validate(['chat_id' => 'required|string|max:40|regex:/^-?[0-9]+$/']);
        $message = OutboundMessage::create(['user_id' => $r->user()->id, 'audience' => 'admin', 'chat_id' => $d['chat_id'], 'subject' => 'Uji Telegram Pharma', 'body' => 'Pesan uji Anteraja Pharma. Ini demonstrasi, bukan informasi pengiriman nyata.', 'action_url' => rtrim(config('pharma.frontend_url'), '/'), 'dedupe_key' => 'demo-test:'.Str::uuid(), 'status' => 'pending']);
        $service->deliver(1, $message->id);
        return ['status' => $message->fresh()->status, 'message' => $message->fresh()->last_error ?: 'Pesan diterima provider Telegram.'];
    }
}
