<?php

namespace App\Http\Controllers;

use App\Services\AutomationService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function automation(Request $r)
    {
        $secret = config('pharma.automation_secret');
        $stamp = $r->header('X-Pharma-Timestamp', '');
        $signature = $r->header('X-Pharma-Signature', '');
        abort_unless($secret && ctype_digit($stamp) && abs(time() - (int) $stamp) <= 300 && hash_equals(hash_hmac('sha256', $stamp.'.'.$r->getContent(), $secret), $signature), 401);
        $d = $r->validate(['event_id' => 'required|string|max:100', 'type' => 'required|in:daily_report,assignment_reminders', 'date' => 'required_if:type,daily_report|date_format:Y-m-d|before_or_equal:today']);

        return DB::transaction(function () use ($d) {
            $previous = DB::table('webhook_receipts')->where('source', 'automation')->where('external_id', $d['event_id'])->first();
            if ($previous) {
                return json_decode($previous->result, true);
            }
            $result = $d['type'] === 'daily_report' ? ['report_id' => app(ReportService::class)->generate($d['date'])->id] : ['reminders' => app(AutomationService::class)->reminders()];
            DB::table('webhook_receipts')->insert(['source' => 'automation', 'external_id' => $d['event_id'], 'result' => json_encode($result), 'created_at' => now(), 'updated_at' => now()]);

            return $result;
        });
    }

    public function telegram(Request $r)
    {
        $secret = config('pharma.telegram_webhook_secret');
        abort_unless($secret && hash_equals($secret, $r->header('X-Telegram-Bot-Api-Secret-Token', '')), 401);
        $d = $r->validate(['update_id' => 'required|integer', 'message.chat.id' => 'sometimes|integer']);
        $result = ['received' => true];
        DB::table('webhook_receipts')->insertOrIgnore(['source' => 'telegram', 'external_id' => (string) $d['update_id'], 'result' => json_encode($result), 'created_at' => now(), 'updated_at' => now()]);

        // Chat IDs are not authentication. State-changing actions always happen inside the signed-in app.
        return $result;
    }
}
