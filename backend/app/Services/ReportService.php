<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\DailyReport;
use App\Models\Issue;
use App\Models\OutboundMessage;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function generate(string $date): DailyReport
    {
        return DB::transaction(function () use ($date) {
            $end = Carbon::parse($date, 'Asia/Jakarta')->endOfDay()->utc();
            $start = Carbon::parse($date, 'Asia/Jakarta')->startOfDay()->utc();
            $watermark = (int) AuditEvent::where('created_at', '<=', $end)->max('id');
            $existing = DailyReport::where('report_date', $date)->latest('revision')->lockForUpdate()->first();
            if ($existing && $existing->last_event_id === $watermark) {
                return $existing;
            }
            $revision = ($existing?->revision ?? 0) + 1;
            $rows = Shipment::with(['events', 'inspections.user', 'approvals.user', 'issues.decisions.user', 'assignments.courier'])->where('created_at', '<=', $end)->get();
            $states = [];
            $snapshot = [];
            foreach ($rows as $s) {
                $events = $s->events->filter(fn ($e) => $e->created_at <= $end);
                $status = $events->last()?->to_status ?? 'inspection';
                $states[$status] = ($states[$status] ?? 0) + 1;
                $snapshot[] = ['tracking_number' => $s->tracking_number, 'status' => $status, 'destination_city' => $s->destination_city,
                    'events' => $events->values()->toArray(),
                    'inspections' => $s->inspections->filter(fn ($r) => $r->created_at <= $end)->values()->toArray(),
                    'approvals' => $s->approvals->filter(fn ($r) => $r->created_at <= $end)->values()->toArray(),
                    'issues' => $s->issues->filter(fn ($r) => $r->created_at <= $end)->values()->toArray(),
                    'assignments' => $s->assignments->filter(fn ($r) => $r->created_at <= $end)->values()->toArray()];
            }
            $summary = ['total' => count($snapshot), 'by_status' => $states, 'events_today' => AuditEvent::whereBetween('created_at', [$start, $end])->count(),
                'issues_today' => Issue::whereBetween('created_at', [$start, $end])->count(),
                'blocked_dispatch_today' => AuditEvent::where('type', 'dispatch_blocked')->whereBetween('created_at', [$start, $end])->count()];
            $report = DailyReport::create(['report_date' => $date, 'revision' => $revision, 'last_event_id' => $watermark, 'summary' => $summary, 'shipment_snapshot' => $snapshot]);
            foreach (User::where('role', 'admin')->get() as $u) {
                $statusText = implode("\n", array_map(fn ($status) => (WorkflowService::LABELS[$status] ?? $status).': '.$states[$status], array_keys($states)));
                OutboundMessage::firstOrCreate(['dedupe_key' => "report:{$date}:{$revision}:{$u->id}"], [
                    'user_id' => $u->id, 'audience' => 'admin', 'chat_id' => $u->telegram_chat_id, 'subject' => 'Laporan harian Pharma',
                    'body' => "Laporan Pharma {$date}\n{$summary['total']} kiriman tercatat. {$summary['issues_today']} kendala hari ini. {$summary['blocked_dispatch_today']} percobaan berangkat diblokir.\nRingkasan:\n".$statusText,
                    'action_url' => rtrim(config('pharma.frontend_url'), '/').'/laporan',
                ]);
            }

            return $report;
        }, 3);
    }
}
