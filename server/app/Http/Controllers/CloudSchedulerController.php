<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Services\AutomationService;
use App\Services\MessageService;
use App\Services\ReportService;
use App\Services\TelemetryAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CloudSchedulerController extends Controller
{
    public function __invoke(Request $request)
    {
        $secret = config('pharma.cron_secret');
        abort_unless($secret && hash_equals('Bearer '.$secret, $request->header('Authorization', '')), 401);
        $data = $request->validate(['mode' => 'sometimes|in:tick,daily']);
        $result = Cache::lock('pharma-cloud-scheduler', 55)->get(function () use ($data) {
            $today = now('Asia/Jakarta');
            $reportId = null;
            if (($today->hour >= 17 || ($data['mode'] ?? 'tick') === 'daily') && ! DailyReport::where('report_date', $today->toDateString())->exists()) {
                $reportId = app(ReportService::class)->generate($today->toDateString())->id;
            }

            return [
                'report_id' => $reportId,
                'reminders' => app(AutomationService::class)->reminders(),
                'telemetry_disruptions' => app(TelemetryAutomationService::class)->detectTelemetryDisruptions(),
                'messages' => app(MessageService::class)->deliver(2),
            ];
        });

        return response()->json($result === false ? ['skipped' => 'scheduler_busy'] : $result, $result === false ? 202 : 200)->header('Cache-Control', 'no-store');
    }
}
