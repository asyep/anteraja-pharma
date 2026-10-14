<!doctype html>
<html lang="id"><head><meta charset="utf-8"><style>
@page { margin: 32px; } body { font-family: 'DejaVu Sans'; font-size: 9px; color: #203b38; line-height: 1.5; }
h1 { font-size: 18px; color: #00685c; } h2 { font-size: 12px; margin-top: 20px; border-bottom: 1px solid #dce7e3; padding-bottom: 5px; }
table { width: 100%; border-collapse: collapse; } th,td { padding: 5px; text-align: left; border-bottom: 1px solid #e0e8e5; overflow-wrap: anywhere; }
th { background: #eef5f2; } .small { color: #5b716c; font-size: 8px; } .proof { max-width: 230px; max-height: 140px; } .row { page-break-inside: avoid; }
</style></head><body>
@if($logo)<img src="{{ $logo }}" width="140" alt="Anteraja Pharma">@endif
<img src="{{ $qr }}" width="76" style="float:right" alt="QR verifikasi">
<h1>Laporan pengiriman farmasi</h1>
<p>{{ $report->snapshot['tracking_number'] }} | {{ $report->final_status === 'COMPLETED_SAFE' ? 'Selesai - aman' : 'Selesai - ada penyimpangan' }}</p>
<p class="small">ID {{ $report->report_id }} | {{ $report->completed_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB<br>Prototype hackathon - {{ $report->snapshot['is_demo'] ? 'data demonstrasi' : 'catatan operasional' }}. Bukan sertifikat mutu atau sertifikasi CPOB/GDP.</p>
<h2>1. Informasi pengiriman</h2>
<table><tr><th>Pengirim</th><td>{{ $report->snapshot['sender_name'] }}</td><th>Penerima</th><td>{{ $report->snapshot['recipient_name'] }}</td></tr>
<tr><th>Asal</th><td>{{ $report->snapshot['origin_city'] }}</td><th>Tujuan</th><td>{{ $report->snapshot['destination_city'] }}</td></tr>
<tr><th>Isi</th><td>{{ $report->snapshot['item_summary'] }}</td><th>Koli / berat</th><td>{{ $report->snapshot['package_count'] }} / {{ $report->snapshot['weight_kg'] }} kg</td></tr>
<tr><th>Rentang</th><td colspan="3">{{ $report->snapshot['temperature_min'] ?? '-' }} sampai {{ $report->snapshot['temperature_max'] ?? '-' }} derajat C, sesuai instruksi paket.</td></tr></table>
<h2>2. Pemeriksaan awal</h2>
@foreach($report->snapshot['inspections'] as $exam)<section class="row">
<p>{{ $exam['checker_name'] }} | {{ $exam['passed'] ? 'Lulus' : 'Belum lulus' }} | {{ $exam['created_at'] }}<br>Kemasan {{ $exam['packaging'] ? 'sesuai' : 'bermasalah' }}, segel {{ $exam['seal'] ? 'utuh' : 'bermasalah' }}, label {{ $exam['label'] ? 'sesuai' : 'bermasalah' }}, suhu {{ $exam['temperature'] ?? '-' }} derajat C.<br>{{ $exam['note'] }}</p>
@if(isset($images[$exam['id']]))<img class="proof" src="{{ $images[$exam['id']] }}" alt="Bukti pemeriksaan">@endif
</section>@endforeach
<h2>3. Telemetry suhu dan lokasi</h2>
<p>Min / maks terukur: {{ $report->snapshot['temperature_summary']['min'] ?? '-' }} / {{ $report->snapshot['temperature_summary']['max'] ?? '-' }} derajat C. {{ count($report->snapshot['tracking_points']) }} titik tercatat.</p>
<table><tr><th>Waktu</th><th>Koordinat</th><th>Suhu</th><th>Sumber</th></tr>
@foreach($report->snapshot['tracking_points'] as $point)<tr><td>{{ $point['recorded_at'] }}</td><td>{{ $point['latitude'] }}, {{ $point['longitude'] }}</td><td>{{ $point['temperature'] ?? '-' }}</td><td>{{ $point['simulated'] ? 'Simulasi' : 'Perangkat / manual' }}</td></tr>@endforeach</table>
<h2>4. Berita acara penyimpangan dan keputusan</h2>
@forelse($report->snapshot['issues'] as $issue)<section class="row"><p><strong>{{ $issue['type'] }}</strong> | {{ $issue['created_at'] }}<br>{{ $issue['description'] }}</p>
@foreach($issue['decisions'] as $decision)<p>{{ $decision['action'] }} | {{ $decision['created_at'] }}<br>Alasan Admin: {{ $decision['reason'] }}</p>@endforeach</section>@empty<p>Tidak ada kendala yang tercatat.</p>@endforelse
<h2>5. Bukti penyerahan / pengembalian</h2>
<p>Penerima: {{ $report->snapshot['pod']['received_by'] ?? '-' }}<br>Persetujuan nama: {{ $report->snapshot['pod']['receiver_signature'] ?? '-' }} (bukan tanda tangan digital tersertifikasi).</p>
@if($pod)<img class="proof" src="{{ $pod }}" alt="Bukti penyerahan">@endif
<h2>6. Jejak audit</h2>
<table><tr><th>Waktu / pelaku</th><th>Kegiatan / alasan</th></tr>@foreach($report->snapshot['events'] as $event)<tr><td>{{ $event['created_at'] }}<br>{{ $event['actor_name'] }}</td><td>{{ $event['title'] }}<br>{{ $event['reason'] }}</td></tr>@endforeach</table>
<p class="small">QR hanya memverifikasi digest laporan pada aplikasi ini. Unduhan laporan dan foto membutuhkan akun yang berhak. Metadata arsip PDF/A-3 diaktifkan; validasi kepatuhan penuh dilakukan terpisah sebelum penggunaan regulasi.</p>
</body></html>
