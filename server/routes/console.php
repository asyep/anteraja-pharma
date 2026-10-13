<?php

use App\Services\AutomationService;
use App\Services\MessageService;
use App\Services\ReportService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('pharma:messages {--limit=30}', function () {
    $this->line(json_encode(app(MessageService::class)->deliver((int) $this->option('limit'))));
})->purpose('Kirim pesan yang tersimpan tanpa memperlambat permintaan pengguna');
Artisan::command('pharma:report {date?}', function () {
    $date = $this->argument('date') ?? now('Asia/Jakarta')->toDateString();
    if (\Illuminate\Support\Facades\Validator::make(['date'=>$date],['date'=>'required|date_format:Y-m-d|before_or_equal:today'])->fails()) {
        $this->error('Tanggal laporan harus valid dan tidak di masa depan.');

        return 1;
    }
    $report = app(ReportService::class)->generate($date);
    $this->info('Laporan #'.$report->id.' tersimpan.');
})->purpose('Buat snapshot laporan harian dan antrekan pesan Admin');
Artisan::command('pharma:reminders', function () {
    $this->info(app(AutomationService::class)->reminders().' pengingat baru.');
});
Schedule::command('pharma:messages')->everyMinute()->withoutOverlapping();
Schedule::command('pharma:reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('pharma:report')->dailyAt('17:00')->timezone('Asia/Jakarta')->withoutOverlapping();
