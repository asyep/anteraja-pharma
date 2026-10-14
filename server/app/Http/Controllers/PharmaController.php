<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\OutboundMessage;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ReportService;
use App\Services\TelemetryAutomationService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PharmaController extends Controller
{
    public function session(Request $r)
    {
        return response()->json(['user' => $r->user(), 'csrf_token' => csrf_token()]);
    }

    public function login(Request $r)
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $data = $r->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:256']);
        if (! Auth::attempt($data)) {
            return response()->json(['message' => 'Email atau kata sandi belum cocok. Periksa lalu coba lagi.'], 422);
        }
        $r->session()->regenerate();

        return response()->json(['user' => $r->user(), 'csrf_token' => csrf_token()]);
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->json(['message' => 'Kamu sudah keluar.', 'csrf_token' => csrf_token()]);
    }

    private function visible(Request $r)
    {
        $q = Shipment::query();
        $u = $r->user();
        if ($u->role === 'buyer') {
            $q->where('buyer_id', $u->id);
        }
        if ($u->role === 'checker') {
            $q->where('inspector_id', $u->id);
        }
        if ($u->role === 'courier') {
            $q->whereHas('assignments', fn ($a) => $a->where('courier_id', $u->id));
        }

        return $q;
    }

    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:120', 'status' => ['nullable', Rule::in(array_keys(WorkflowService::LABELS))], 'per_page' => 'nullable|integer|min:5|max:100']);
        $q = $this->visible($r);
        if ($r->filled('q')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $r->q).'%';
            $q->where(fn ($x) => $x->where('tracking_number', 'like', $term)->orWhere('recipient_name', 'like', $term)->orWhere('destination_city', 'like', $term));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        $q->with(['buyer:id,name', 'assignments.courier:id,name', 'inspections' => fn ($q) => $q->orderByDesc('id')]);
        if ($r->user()->role === 'admin' || $r->user()->role === 'checker') {
            $q->with('inspector:id,name');
        }
        $results = $q->latest('id')->paginate((int) $r->input('per_page', 20));
        if ($r->user()->role === 'buyer') {
            $results->getCollection()->each(fn ($shipment) => $shipment->makeHidden(['inspector_id', 'inspector']));
        }

        return $results;
    }

    public function dashboard(Request $r)
    {
        $totals = $this->visible($r)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
        $stages = match ($r->user()->role) {
            'admin' => ['held', 'pending_approval', 'inspection', 'assigned'],
            'checker' => ['inspection'],
            'courier' => ['held', 'returning', 'assigned', 'accepted', 'in_transit', 'arrived'],
            default => ['held', 'returning', 'arrived', 'in_transit', 'delivered', 'inspection', 'pending_approval', 'approved', 'assigned', 'accepted']
        };
        $priority = $this->visible($r)->whereIn('status', $stages)->with(['buyer:id,name', 'inspections' => fn ($q) => $q->orderByDesc('id')])
            ->orderByRaw("CASE status WHEN 'held' THEN 0 WHEN 'returning' THEN 1 WHEN 'pending_approval' THEN 2 WHEN 'assigned' THEN 3 ELSE 4 END")
            ->latest('updated_at')->limit(6)->get();

        return ['totals' => $totals, 'priority' => $priority, 'labels' => WorkflowService::LABELS];
    }

    public function detail(Request $r, int $id)
    {
        $s = $this->visible($r)->findOrFail($id);
        $s->load([
            'buyer:id,name', 'inspector:id,name', 'inspections.user:id,name', 'approvals.user:id,name',
            'assignments.courier:id,name', 'issues.user:id,name', 'issues.decisions.user:id,name',
            'events', 'trackingPoints', 'aiEvaluations.trackingPoint', 'finalReport',
        ]);
        $s->inspections->each(fn ($inspection) => $inspection->setAttribute(
            'photo_proof_url',
            $inspection->photo_proof_path
                ? "/api/shipments/{$s->id}/inspections/{$inspection->id}/photo"
                : null,
        ));
        if ($r->user()->role === 'buyer') {
            $s->unsetRelation('assignments');
            $s->unsetRelation('inspector');
            $s->makeHidden('inspector_id');
            $s->inspections->each(function ($inspection) {
                $inspection->unsetRelation('user');
                $inspection->makeHidden('user_id');
            });
            $s->events->each(function ($event) use ($s) {
                if ($event->user_id === $s->inspector_id || $event->type === 'inspect') {
                    $event->actor_name = 'Checker';
                }
                $event->makeHidden('user_id');
            });
            if ($s->finalReport) {
                $snapshot = $s->finalReport->snapshot;
                foreach ($snapshot['events'] as &$event) {
                    if ($event['type'] === 'inspect') {
                        $event['actor_name'] = 'Checker';
                    }
                }
                unset($event);
                $s->finalReport->snapshot = $snapshot;
            }
        }

        return $s;
    }

    public function inspectionPhoto(Request $r, int $id, int $inspection)
    {
        $shipment = $this->visible($r)->findOrFail($id);
        $record = $shipment->inspections()->findOrFail($inspection);
        abort_unless($record->photo_proof_path, 404);

        return response()->file(Storage::disk('local')->path($record->photo_proof_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function directory(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);

        return User::whereIn('role', ['buyer', 'checker', 'courier'])->get(['id', 'name', 'role']);
    }

    public function create(Request $r, WorkflowService $flow)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate([
            'buyer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'buyer')],
            'inspector_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'checker')],
            'sender_name' => 'required|string|max:150', 'sender_chat_id' => 'nullable|string|max:40|regex:/^-?[0-9]+$/',
            'recipient_name' => 'required|string|max:150', 'recipient_phone' => 'required|string|max:25',
            'address' => 'required|string|max:1000', 'origin_city' => 'required|string|max:100', 'destination_city' => 'required|string|max:100',
            'item_summary' => 'required|string|max:180', 'package_count' => 'required|integer|min:1|max:100',
            'handling_instructions' => 'required|string|max:2000', 'scheduled_at' => 'required|date|after:now',
            'temperature_profile' => 'required|in:ambient,controlled',
            'temperature_min' => 'required_if:temperature_profile,controlled|nullable|numeric|between:-50,60',
            'temperature_max' => 'required_if:temperature_profile,controlled|nullable|numeric|between:-50,60|gte:temperature_min',
        ]);
        $s = DB::transaction(function () use ($d, $r, $flow) {
            $s = Shipment::create($d + ['tracking_number' => 'PHA-'.strtoupper(bin2hex(random_bytes(5))), 'created_by' => $r->user()->id, 'last_location' => $d['origin_city'], 'status' => 'inspection']);
            $flow->event($s, $r->user(), 'created', 'Kiriman dibuat, pemeriksaan dijadwalkan', 'Periksa kemasan, segel, dan label sebelum meminta persetujuan Admin.');

            return $s;
        });

        return response()->json($s, 201);
    }

    public function action(Request $r, int $id, string $action, WorkflowService $flow)
    {
        $this->visible($r)->findOrFail($id);
        $rules = [
            'version' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:2000',
            'packaging' => 'sometimes|boolean', 'seal' => 'sometimes|boolean', 'label' => 'sometimes|boolean',
            'temperature' => 'nullable|numeric|between:-50,60', 'courier_id' => 'sometimes|integer|exists:users,id',
            'location' => 'sometimes|string|max:180', 'issue_type' => 'sometimes|in:seal,packaging,temperature,label,recipient_unavailable,address,other',
            'decision' => 'sometimes|in:return,recheck,resume', 'ready' => 'sometimes|boolean', 'received_by' => 'sometimes|string|min:2|max:150',
            'photo_proof' => $action === 'inspect'
                ? 'required|image|mimes:jpg,jpeg,png,webp|max:5120'
                : 'prohibited',
        ];
        $d = $r->validate($rules);
        $fields = match ($action) {
            'inspect' => ['packaging', 'seal', 'label'],'assign' => ['courier_id'],'depart','arrive','return_complete' => ['location'],'issue' => ['issue_type'],'decide' => ['decision'],'ready' => ['ready'],'deliver' => ['received_by'],default => []
        };
        foreach ($fields as $field) {
            if (! array_key_exists($field, $d)) {
                throw ValidationException::withMessages([$field => 'Lengkapi kolom ini sebelum melanjutkan.']);
            }
        }

        return $flow->apply($id, $r->user(), $action, $d);
    }

    public function telemetry(Request $r, int $id, TelemetryAutomationService $automation)
    {
        abort_unless($r->user()->role === 'courier', 403);
        $shipment = $this->visible($r)->findOrFail($id);
        $data = $r->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kmh' => 'nullable|numeric|between:0,300',
            'temperature' => 'nullable|numeric|between:-50,60',
        ]);

        return $automation->record($shipment, $r->user(), $data)->load('trackingPoint');
    }

    public function messages(Request $r)
    {
        $q = OutboundMessage::query();
        if ($r->user()->role !== 'admin') {
            $q->where('user_id', $r->user()->id);
        }

        return $q->latest('id')->paginate(30);
    }

    public function retry(Request $r, int $id)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $m = OutboundMessage::findOrFail($id);
        abort_if(in_array($m->status, ['sent', 'processing']), 422, 'Pesan sudah terkirim atau sedang diproses.');
        $m->update(['status' => 'pending', 'next_attempt_at' => null, 'last_error' => null, 'attempts' => 0]);

        return ['message' => 'Pesan dijadwalkan ulang.'];
    }

    public function reports(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);

        return DailyReport::select('id', 'report_date', 'revision', 'summary', 'created_at')->latest('report_date')->latest('revision')->paginate(20);
    }

    public function report(Request $r, ReportService $service)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['date' => 'required|date_format:Y-m-d|before_or_equal:today']);

        return $service->generate($d['date']);
    }

    public function export(Request $r, int $id)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $report = DailyReport::findOrFail($id);

        return response()->streamDownload(function () use ($report) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Resi', 'Status', 'Kota tujuan', 'Waktu', 'Pelaku', 'Kegiatan', 'Alasan']);
            foreach ($report->shipment_snapshot as $s) {
                foreach ($s['events'] as $e) {
                    $cells = [$s['tracking_number'], WorkflowService::LABELS[$s['status']], $s['destination_city'], $e['created_at'], $e['actor_name'], $e['title'], $e['reason']];
                    fputcsv($f, array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/u', (string) $v) ? "'".$v : $v, $cells));
                }
            }
            fclose($f);
        }, 'pharma-'.$report->report_date.'-v'.$report->revision.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function settings(Request $r)
    {
        $d = $r->validate(['telegram_chat_id' => 'nullable|string|max:40|regex:/^-?[0-9]+$/']);
        $r->user()->update($d);

        return ['message' => 'Tujuan notifikasi sudah disimpan.'];
    }
}
