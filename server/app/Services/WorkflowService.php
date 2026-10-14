<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public const LABELS = [
        'inspection' => 'Menunggu pemeriksaan', 'pending_approval' => 'Menunggu persetujuan',
        'approved' => 'Siap ditugaskan', 'assigned' => 'Menunggu konfirmasi kurir',
        'accepted' => 'Kurir siap mengambil', 'in_transit' => 'Dalam perjalanan',
        'arrived' => 'Sudah di lokasi penerima', 'held' => 'Ditahan untuk pemeriksaan',
        'returning' => 'Dalam proses retur', 'returned' => 'Sudah dikembalikan', 'delivered' => 'Sudah diterima', 'cancelled' => 'Dibatalkan Admin',
    ];

    public function event(Shipment $s, ?User $user, string $type, string $title, string $reason, ?string $from = null, array $metadata = []): AuditEvent
    {
        $event = $s->events()->create(['user_id' => $user?->id, 'actor_name' => $user?->name ?? 'Sistem',
            'type' => $type, 'title' => $title, 'reason' => $reason, 'from_status' => $from, 'to_status' => $s->status,
            'metadata' => $metadata + ['role' => $user?->role ?? 'automation', 'ip' => app()->runningInConsole() ? null : request()->ip(), 'simulated' => (bool)$s->is_demo]]);
        app(MessageService::class)->forEvent($s, $event);

        return $event;
    }

    private function need(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['action' => $message]);
        }
    }

    public function apply(int $id, User $user, string $action, array $data): Shipment
    {
        try {
            return DB::transaction(function () use ($id, $user, $action, $data) {
                $s = Shipment::lockForUpdate()->findOrFail($id);
                if (!empty($data['request_id']) && $s->events()->where('user_id', $user->id)->where('type', $action)->where('metadata->request_id', $data['request_id'])->exists()) return $s;
                abort_unless((int) $data['version'] === $s->version, 409, 'Data kiriman sudah berubah. Muat ulang sebelum melanjutkan.');
                $before = $s->status;
                $this->need(!$s->closed_at, 'Kiriman sudah ditutup. Bukti dan riwayat tidak dapat diubah.');
                $admin = $user->role === 'admin';
                $checker = $user->role === 'checker' && $s->inspector_id === $user->id;
                $assignment = $s->assignments()->latest('id')->first();
                $courier = $user->role === 'courier' && $assignment && $assignment->courier_id === $user->id && $assignment->status === 'accepted';
                $reason = $data['reason'] ?? '';
                $title = '';
                switch ($action) {
                    case 'inspect':
                        abort_unless($checker, 403);
                        $this->need(in_array($s->status, ['inspection', 'pending_approval']) || ($s->status === 'held' && ! $s->issues()->where('status', 'open')->exists()), 'Pemeriksaan belum bisa diubah pada tahap ini. Tunggu instruksi Admin.');
                        $passed = $data['packaging'] && $data['seal'] && $data['label'];
                        if ($s->temperature_profile === 'controlled') {
                            $this->need(isset($data['temperature']), 'Catat suhu yang diukur untuk paket dengan penanganan suhu.');
                            $passed = $passed && $data['temperature'] >= $s->temperature_min && $data['temperature'] <= $s->temperature_max;
                        }
                        $photoProof = $data['photo_proof'] ?? null;
                        $this->need($photoProof !== null || config('pharma.demo_seed'), 'Foto bukti pemeriksaan wajib diunggah.');
                        $proofPath = $photoProof ? app(ProofService::class)->store($s->id, $photoProof) : null;
                        $s->inspections()->create([
                            'user_id' => $user->id,
                            'packaging' => $data['packaging'],
                            'seal' => $data['seal'],
                            'label' => $data['label'],
                            'temperature' => $data['temperature'] ?? null,
                            'note' => $reason,
                            'passed' => $passed,
                            'photo_proof_path' => $proofPath,
                        ]);
                        $s->status = $passed ? 'pending_approval' : 'held';
                        $title = $passed ? 'Pemeriksaan lengkap, menunggu Admin' : 'Pemeriksaan belum lengkap';
                        break;
                    case 'approve':
                        abort_unless($admin, 403);
                        $latest = $s->inspections()->latest('id')->first();
                        $this->need($latest && $latest->passed, 'Paket belum boleh berangkat. Lengkapi pemeriksaan kemasan, segel, dan label terlebih dahulu.');
                        $this->need($s->status === 'pending_approval', 'Kiriman tidak sedang menunggu persetujuan.');
                        $s->approvals()->create(['user_id' => $user->id, 'inspection_id' => $latest->id, 'decision' => 'approved', 'reason' => $reason]);
                        $s->status = 'approved';
                        $title = 'Admin menyetujui pengiriman';
                        break;
                    case 'hold':
                        abort_unless($admin, 403);
                        $this->need(in_array($s->status, ['inspection', 'pending_approval', 'approved']), 'Gunakan keputusan kendala untuk menahan kiriman yang sudah ditugaskan.');
                        $s->approvals()->create(['user_id' => $user->id, 'inspection_id' => $s->inspections()->latest('id')->value('id'), 'decision' => 'held', 'reason' => $reason]);
                        $s->status = 'held';
                        $title = 'Admin menahan pengiriman';
                        break;
                    case 'cancel':
                        abort_unless($admin, 403);
                        $this->need(in_array($s->status, ['inspection', 'pending_approval', 'approved']), 'Kiriman yang sudah berjalan harus ditangani melalui keputusan kendala.');
                        $s->status = 'cancelled';
                        $s->completed_at = now();
                        $s->closed_at = now();
                        $title = 'Admin membatalkan kiriman sebelum penugasan';
                        break;
                    case 'assign':
                        abort_unless($admin, 403);
                        $this->need($s->status === 'approved', 'Penugasan diblokir. Paket harus selesai diperiksa dan disetujui Admin.');
                        $target = User::findOrFail($data['courier_id']);
                        $this->need($target->role === 'courier', 'Pilih akun kurir untuk penugasan.');
                        $this->need($target->is_active, 'Kurir ini tidak sedang aktif. Pilih petugas aktif.');
                        if ($s->temperature_profile === 'controlled') $this->need($target->cold_box_capacity_kg !== null && $target->cold_box_capacity_kg >= $s->weight_kg, 'Kapasitas cold-box kurir belum memenuhi berat kiriman.');
                        $s->assignments()->create(['courier_id' => $target->id, 'assigned_by' => $user->id, 'status' => 'pending', 'reason' => $reason]);
                        $s->status = 'assigned';
                        $title = 'Tugas dikirim ke kurir '.$target->name;
                        break;
                    case 'accept': case 'reject':
                        abort_unless($user->role === 'courier' && $assignment && $assignment->courier_id === $user->id, 403);
                        $this->need($s->status === 'assigned' && $assignment->status === 'pending', 'Tugas ini sudah dijawab atau tidak lagi tersedia.');
                        $assignment->update(['status' => $action === 'accept' ? 'accepted' : 'rejected', 'reason' => $reason, 'responded_at' => now()]);
                        $s->status = $action === 'accept' ? 'accepted' : 'approved';
                        $title = $action === 'accept' ? 'Kurir menerima tugas' : 'Tugas ditolak, Admin perlu menugaskan ulang';
                        break;
                    case 'depart':
                        abort_unless($courier, 403);
                        $this->need($s->status === 'accepted', 'Paket belum siap berangkat.');
                        $s->status = 'in_transit';
                        $s->last_location = $data['location'];
                        $title = 'Kurir memulai perjalanan';
                        break;
                    case 'arrive':
                        abort_unless($courier, 403);
                        $this->need($s->status === 'in_transit', 'Paket belum dalam perjalanan.');
                        $s->status = 'arrived';
                        $s->last_location = $data['location'];
                        $title = 'Kurir sudah di lokasi penerima';
                        break;
                    case 'issue':
                        abort_unless($courier, 403);
                        $this->need(in_array($s->status, ['accepted', 'in_transit', 'arrived']), 'Kendala belum bisa dilaporkan pada tahap ini.');
                        $this->need(! $s->issues()->where('status', 'open')->exists(), 'Kendala masih menunggu keputusan Admin.');
                        $s->issues()->create(['user_id' => $user->id, 'type' => $data['issue_type'], 'description' => $reason, 'previous_status' => $before]);
                        $s->status = 'held';
                        $title = 'Pengiriman ditahan: ada kendala';
                        break;
                    case 'decide':
                        abort_unless($admin, 403);
                        $this->need($s->status === 'held', 'Kiriman tidak sedang ditahan.');
                        $issue = $s->issues()->where('status', 'open')->lockForUpdate()->first();
                        $this->need((bool) $issue, 'Tidak ada kendala yang menunggu keputusan.');
                        $decision = $data['decision'];
                        if ($decision === 'reroute') $this->need(!in_array($issue->type, ['seal', 'packaging', 'label']), 'Kerusakan integritas wajib retur atau pemeriksaan ulang, bukan pengalihan biasa.');
                        if ($decision === 'resume') {
                            $this->need(in_array($issue->type, ['recipient_unavailable', 'address', 'other', 'signal_loss', 'temperature']), 'Segel atau kemasan bermasalah wajib diretur atau diperiksa ulang.');
                            if (in_array($issue->type, ['temperature', 'signal_loss'])) {
                                $point = $s->trackingPoints()->reorder('recorded_at', 'desc')->first();
                                $this->need($point && $point->recorded_at->gte(now()->subMinutes(2)), 'Dapatkan telemetry terbaru sebelum melanjutkan.');
                                if ($s->temperature_profile === 'controlled') $this->need($point->temperature !== null && $point->temperature >= $s->temperature_min && $point->temperature <= $s->temperature_max, 'Suhu terbaru belum kembali ke rentang penanganan.');
                            }
                        }
                        $issue->decisions()->create(['user_id' => $user->id, 'action' => $decision, 'reason' => $reason]);
                        $issue->update(['status' => 'resolved']);
                        if (in_array($decision, ['return', 'recheck'])) {
                            foreach ($s->issues()->where('status', 'open')->get() as $other) {
                                $other->decisions()->create(['user_id' => $user->id, 'action' => $decision, 'reason' => $reason]);
                                $other->update(['status' => 'resolved']);
                            }
                        }
                        $s->status = match ($decision) {
                            'return' => 'returning','recheck' => 'inspection','reroute' => 'in_transit','resume' => $issue->previous_status
                        };
                        if (in_array($decision, ['resume', 'reroute']) && $s->issues()->where('status', 'open')->exists()) $s->status = 'held';
                        if ($decision === 'recheck' && $assignment) {
                            $assignment->update(['status' => 'cancelled', 'reason' => 'Perlu pemeriksaan dan persetujuan ulang.']);
                        }
                        $title = match ($decision) {
                            'return' => 'Admin meminta paket diretur','recheck' => 'Admin meminta pemeriksaan ulang','reroute' => 'Admin mengalihkan paket ke hub atau cold-storage','resume' => 'Admin memberi instruksi untuk melanjutkan'
                        };
                        break;
                    case 'return_complete':
                        abort_unless($courier, 403);
                        $this->need($s->status === 'returning', 'Retur belum diinstruksikan Admin.');
                        $s->status = 'returned';
                        $s->last_location = $data['location'];
                        $s->completed_at = now();
                        $s->pod_proof_path = isset($data['photo_proof']) ? app(ProofService::class)->store($s->id, $data['photo_proof']) : null;
                        $s->received_by = $data['received_by'] ?? 'Petugas hub retur';
                        $s->receiver_signature = $data['receiver_signature'] ?? $s->received_by;
                        $title = 'Kurir menyelesaikan retur';
                        break;
                    case 'deliver':
                        abort_unless($courier, 403);
                        $this->need($s->status === 'arrived', 'Selesaikan perjalanan dan pastikan paket sudah di lokasi penerima.');
                        $this->need(! $s->issues()->where('status', 'open')->exists(), 'Ada kendala yang belum diputuskan.');
                        if ($s->temperature_profile === 'controlled') {
                            $point = $s->trackingPoints()->reorder('recorded_at', 'desc')->first();
                            $this->need($point && $point->recorded_at->gte(now()->subMinutes(2)) && $point->temperature !== null && $point->temperature >= $s->temperature_min && $point->temperature <= $s->temperature_max, 'Verifikasi suhu terakhir dalam rentang sebelum serah terima.');
                        }
                        $s->status = 'delivered';
                        $s->completed_at = now();
                        $s->pod_proof_path = isset($data['photo_proof']) ? app(ProofService::class)->store($s->id, $data['photo_proof']) : null;
                        $s->received_by = $data['received_by'];
                        $s->receiver_signature = $data['receiver_signature'] ?? $s->received_by;
                        $title = 'Paket diterima oleh '.$data['received_by'];
                        break;
                    case 'ready':
                        abort(403, 'Vendor / Customer memiliki akses pemantauan, bukan perubahan kiriman.');
                    case 'confirm':
                        abort(403, 'Penerimaan dicatat kurir melalui bukti serah terima.');
                    default: abort(422, 'Tindakan tidak dikenali.');
                }
                $s->version++;
                $s->save();
                $this->event($s, $user, $action, $title, $reason, $before, ['request_id' => $data['request_id'] ?? null]);
                if (in_array($action, ['deliver', 'return_complete'], true)) {
                    $report = app(FinalShipmentReportService::class)->publish($s);
                    $this->event(
                        $s,
                        null,
                        'report_published',
                        'Laporan akhir diterbitkan',
                        'Laporan perjalanan dibagikan kepada Admin, kurir, dan Vendor / Customer.',
                        $s->status,
                        ['report_id' => $report->report_id],
                    );
                    $s->closed_at = now();
                    $s->save();
                }

                return $s;
            }, 3);
        } catch (ValidationException $e) {
            // A denied dispatch is evidence, not a completed workflow action.
            if (in_array($action, ['approve', 'assign']) && $user->role === 'admin') {
                $s = Shipment::findOrFail($id);
                $this->event($s,$user,'dispatch_blocked','Pengiriman diblokir oleh pemeriksaan', $e->validator->errors()->first(),$s->status);
            }
            throw $e;
        }
    }
}
