<?php

namespace Database\Seeders;

use App\Models\Shipment;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = config('pharma.demo_password');
        if (! $password) {
            throw new \RuntimeException('Pasang DEMO_PASSWORD lokal sebelum menjalankan seed.');
        }
        $people = [['admin', 'Rina Pramesti', 'admin@pharma.test'], ['courier', 'Dimas Saputra', 'satria@pharma.test'], ['buyer', 'Nadia Putri', 'pembeli@pharma.test'], ['buyer', 'Arif Wijaya', 'pembeli.lain@pharma.test'], ['courier', 'Satria Aditya', 'satria.lain@pharma.test']];
        $users = [];
        foreach ($people as [$role,$name,$email]) {
            $users[$email] = User::firstOrCreate(['email' => $email], ['name' => $name, 'role' => $role, 'password' => $password, 'telegram_chat_id' => config('pharma.'.$role.'_chat_id')]);
        }
        $admin = $users['admin@pharma.test'];
        $courier = $users['satria@pharma.test'];
        $buyer = $users['pembeli@pharma.test'];
        $flow = app(WorkflowService::class);
        config(['pharma.demo_seed' => true]);
        $places = [['Apotek Sehat Menteng', 'Jakarta Pusat', 'Jl. Pegangsaan Timur No. 16, Menteng'], ['Klinik Cilandak', 'Jakarta Selatan', 'Jl. Cilandak KKO No. 8'], ['Apotek Harapan Bekasi', 'Bekasi', 'Jl. Ahmad Yani No. 20'], ['Klinik Keluarga Serpong', 'Tangerang Selatan', 'Jl. Pahlawan Seribu No. 12']];
        for ($i = 1; $i <= 32; $i++) {
            $tracking = 'PHA-260'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            if (Shipment::where('tracking_number', $tracking)->exists()) {
                continue;
            }
            $place = $places[($i - 1) % count($places)];
            $s = Shipment::create(['tracking_number' => $tracking, 'buyer_id' => $i % 8 === 0 ? $users['pembeli.lain@pharma.test']->id : $buyer->id, 'inspector_id' => $courier->id, 'created_by' => $admin->id,
                'sender_name' => 'Distribusi Sehat Nusantara', 'sender_chat_id' => config('pharma.sender_chat_id'), 'recipient_name' => $place[0], 'recipient_phone' => '081200000000',
                'address' => $place[2], 'origin_city' => 'Jakarta Timur', 'destination_city' => $place[1], 'item_summary' => 'Paket perlengkapan kesehatan (data contoh)', 'package_count' => 1 + ($i % 3),
                'handling_instructions' => 'Jaga paket tetap tegak. Hindari panas langsung. Periksa segel bersama penerima sebelum serah terima.',
                'temperature_profile' => 'ambient', 'scheduled_at' => now()->addHours(4 + ($i % 8)), 'status' => 'inspection', 'last_location' => 'Hub Cakung']);
            $flow->event($s, $admin, 'created', 'Kiriman dibuat, menunggu pemeriksaan', 'Data contoh untuk latihan operasional.');
            if ($i === 1) {
                continue;
            }
            $act = function ($user, $action, $extra = []) use ($flow, &$s) {
                $s = $flow->apply($s->id, $user, $action, ['version' => $s->version, 'reason' => 'Catatan operasional contoh.'] + $extra);
            };
            $act($courier, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
            if ($i % 7 === 0) {
                continue;
            }
            $act($admin, 'approve');
            if ($i % 6 === 0) {
                continue;
            }
            $act($admin, 'assign', ['courier_id' => $courier->id]);
            if ($i % 5 === 0) {
                continue;
            }
            $act($courier, 'accept');
            $act($courier, 'depart', ['location' => 'Hub Cakung → '.$place[1]]);
            if ($i % 4 === 0) {
                continue;
            }
            if ($i % 3 === 0) {
                $act($courier, 'issue', ['issue_type' => 'seal']);
                if ($i % 9 === 0) {
                    $act($admin, 'decide', ['decision' => 'return']);
                    $act($courier, 'return_complete', ['location' => 'Hub Cakung']);
                }

                continue;
            }
            $act($courier, 'arrive', ['location' => $place[0]]);
            $act($courier, 'deliver', ['received_by' => 'Petugas '.$place[0]]);
        }
        config(['pharma.demo_seed' => false]);
    }
}
