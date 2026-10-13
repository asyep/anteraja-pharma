<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class AutomationService
{
    public function reminders(): int
    {
        $count = 0;
        foreach (Assignment::where('status', 'pending')->whereNull('reminded_at')->where('created_at', '<=', now()->subMinutes(15))->pluck('id') as $id) {
            $count += DB::transaction(function () use ($id) {
                $a = Assignment::lockForUpdate()->find($id);
                if (! $a || $a->status !== 'pending' || $a->reminded_at) {
                    return 0;
                }
                $s = Shipment::lockForUpdate()->find($a->shipment_id);
                if ($s->status !== 'assigned') {
                    return 0;
                }
                $a->update(['reminded_at' => now()]);
                app(WorkflowService::class)->event($s, null, 'reminder', 'Tugas masih menunggu jawaban Satria', 'Satria perlu menerima atau menolak. Admin dapat memantau dari detail tugas.', $s->status);

                return 1;
            });
        }

        return $count;
    }
}
