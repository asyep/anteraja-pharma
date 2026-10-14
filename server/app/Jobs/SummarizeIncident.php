<?php

namespace App\Jobs;

use App\Models\AiEvaluation;
use App\Models\Shipment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Throwable;

class SummarizeIncident implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 15;
    public array $backoff = [30];

    public function __construct(public int $evaluationId) {}

    public function handle(): void
    {
        $evaluation = AiEvaluation::find($this->evaluationId);
        if (!$evaluation || !config('pharma.openai_api_key') || Shipment::find($evaluation->shipment_id)?->closed_at) return;
        $details = $evaluation->anomaly_details;
        // only measured anomaly facts go to the provider, not names or addresses.
        try {
            $response = Http::withToken(config('pharma.openai_api_key'))->connectTimeout(3)->timeout(10)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('pharma.openai_model'), 'store' => false, 'max_completion_tokens' => 160,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Ringkas fakta penyimpangan berikut dalam Bahasa Indonesia, maksimal dua kalimat. Jangan mengubah keputusan HOLD, membuat diagnosis, atau mengarang fakta.'],
                        ['role' => 'user', 'content' => json_encode(['incident_type' => $evaluation->incident_type, 'measured_facts' => $details], JSON_THROW_ON_ERROR)],
                    ],
                ])->throw();
            $summary = $response->json('choices.0.message.content');
            if (!is_string($summary) || !trim($summary)) throw new \RuntimeException('Empty assistant response');
            $details['assistant_summary'] = mb_substr(trim($summary), 0, 1200);
            $details['assistant_status'] = 'available';
        } catch (Throwable) {
            // provider failure never changes the rule result or hides the local reason.
            $details['assistant_status'] = 'unavailable';
        }
        DB::transaction(function () use ($evaluation, $details) {
            $s = Shipment::lockForUpdate()->find($evaluation->shipment_id);
            if ($s && !$s->closed_at) $evaluation->update(['anomaly_details' => $details]);
        });
    }
}
