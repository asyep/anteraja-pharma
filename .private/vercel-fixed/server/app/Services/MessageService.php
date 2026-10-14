<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\OutboundMessage;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function forEvent(Shipment $s, AuditEvent $event): void
    {
        $url = rtrim(config('pharma.frontend_url'), '/').'/kiriman/'.$s->id;
        $body = "{$s->tracking_number}\n{$event->title}\n{$event->reason}\nJadwal: ".$s->scheduled_at->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB';
        $targets = [];
        foreach (User::where('role', 'admin')->get() as $u) {
            $targets[] = [$u->id, 'admin', $u->telegram_chat_id];
        }
        if ($event->type !== 'dispatch_blocked') {
            $targets[] = [$s->buyer_id, 'buyer', $s->buyer->telegram_chat_id];
            $targets[] = [null, 'sender', $s->sender_chat_id];
            $assignment = $s->assignments()->latest('id')->first();
            $courier = $assignment ? User::find($assignment->courier_id) : $s->inspector;
            $audience = $assignment ? 'courier' : 'checker';
            $targets[] = [$courier->id, $audience, $courier->telegram_chat_id];
        }
        foreach ($targets as [$user, $audience, $chat]) {
            $extra = $audience === 'courier' ? "\nInstruksi: {$s->handling_instructions}" : '';
            OutboundMessage::firstOrCreate(['dedupe_key' => "event:{$event->id}:{$audience}:".($user ?? 'external')], [
                'shipment_id' => $s->id, 'event_id' => $event->id, 'user_id' => $user, 'audience' => $audience,
                'chat_id' => $chat, 'subject' => $event->title, 'body' => $body.$extra, 'action_url' => $url,
                'status' => config('pharma.demo_seed', false) ? 'demo' : 'pending',
            ]);
        }
    }

    public function deliver(int $limit = 30, ?int $onlyId = null): array
    {
        $counts = ['sent' => 0, 'failed' => 0, 'unconfigured' => 0];
        // recover work interrupted before the provider responded; do not claim exactly-once delivery.
        OutboundMessage::where('status', 'processing')->where('updated_at', '<', now()->subMinutes(5))->update(['status' => 'retry']);
        $ids = OutboundMessage::whereIn('status', ['pending', 'retry', 'unconfigured'])
            ->when($onlyId !== null, fn ($q) => $q->where('id', $onlyId))
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'retry' THEN 1 ELSE 2 END")->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            $m = DB::transaction(function () use ($id) {
                $row = OutboundMessage::lockForUpdate()->find($id);
                if (! $row || ! in_array($row->status, ['pending', 'retry', 'unconfigured'])) {
                    return null;
                }
                $chat = $row->chat_id ?: ($row->user_id ? User::find($row->user_id)?->telegram_chat_id : config('pharma.sender_chat_id'));
                $chat = $chat ?: config('pharma.'.$row->audience.'_chat_id');
                if (! config('pharma.telegram_token') || ! $chat) {
                    $row->update(['status' => 'unconfigured', 'last_error' => 'Token bot atau chat ID belum dipasang.', 'next_attempt_at' => now()->addMinutes(15)]);

                    return false;
                }
                $row->update(['chat_id' => $chat, 'status' => 'processing', 'attempts' => $row->attempts + 1]);

                return $row;
            });
            if ($m === false) {
                $counts['unconfigured']++;

                continue;
            }
            if (! $m) {
                continue;
            }
            try {
                $payload = ['chat_id' => $m->chat_id, 'text' => $m->body];
                if (str_starts_with($m->action_url, 'https://')) {
                    $payload['reply_markup'] = ['inline_keyboard' => [[['text' => 'Buka kiriman', 'url' => $m->action_url]]]];
                } else {
                    $payload['text'] .= "\nBuka aplikasi: {$m->action_url}";
                }
                $response = Http::connectTimeout(3)->timeout(8)->post('https://api.telegram.org/bot'.config('pharma.telegram_token').'/sendMessage', $payload);
                if ($response->successful() && $response->json('ok') === true) {
                    $m->update(['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => (string) $response->json('result.message_id'), 'last_error' => null, 'next_attempt_at' => null]);
                    $counts['sent']++;
                } else {
                    $permanent = in_array($response->status(), [400, 401, 403, 404]);
                    $delay = min(3600, max(30, (int) $response->json('parameters.retry_after', 30 * (2 ** min($m->attempts, 6)))));
                    $m->update(['status' => $permanent || $m->attempts >= 5 ? 'failed' : 'retry', 'last_error' => "Telegram menolak pengiriman (HTTP {$response->status()}). Periksa konfigurasi atau coba lagi.", 'next_attempt_at' => now()->addSeconds($delay)]);
                    $counts['failed']++;
                }
            } catch (\Throwable $e) {
                $m->update(['status' => $m->attempts >= 5 ? 'failed' : 'retry', 'last_error' => 'Koneksi Telegram belum berhasil. Pesan tersimpan untuk dicoba kembali.', 'next_attempt_at' => now()->addMinutes(1)]);
                // exception URLs can contain the bot token; never log exception text.
                Log::warning('pharma.message.connection_failed', ['message_id' => $m->id, 'exception_type' => get_class($e)]);
                $counts['failed']++;
            }
        }

        return $counts;
    }
}
