<?php

namespace App\Services;

use App\Models\FinalShipmentReport;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class ShipmentPdfService
{
    public function render(FinalShipmentReport $report): string
    {
        $cache = storage_path('framework/cache/data/pdf');
        if (!is_dir($cache)) mkdir($cache, 0700, true);
        $options = new Options(['isRemoteEnabled' => false, 'isJavascriptEnabled' => false, 'isPhpEnabled' => false,
            'defaultFont' => 'DejaVu Sans', 'fontCache' => $cache, 'tempDir' => sys_get_temp_dir(), 'isPdfAEnabled' => true]);
        $pdf = new Dompdf($options);
        $verifyUrl = rtrim(config('pharma.frontend_url'), '/').'/api/report-verification/'.$report->verification_token;
        $qr = (new SvgWriter())->write(new QrCode($verifyUrl, size: 160, margin: 8))->getDataUri();
        $images = [];
        foreach ($report->snapshot['inspections'] as $exam) {
            if (!empty($exam['proof_path'])) {
                [$bytes, $mime] = app(ProofService::class)->bytes($report->shipment_id, $exam['proof_path']);
                $images[$exam['id']] = 'data:'.$mime.';base64,'.base64_encode($bytes);
            }
        }
        $pod = null;
        if (!empty($report->snapshot['pod']['proof_path'])) {
            [$bytes, $mime] = app(ProofService::class)->bytes($report->shipment_id, $report->snapshot['pod']['proof_path']);
            $pod = 'data:'.$mime.';base64,'.base64_encode($bytes);
        }
        $logoPath = public_path('brand/pharma-logo.png');
        $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
        $pdf->loadHtml(view('reports.shipment', compact('report', 'qr', 'images', 'pod', 'logo'))->render());
        $pdf->setPaper('A4');
        $pdf->render();
        return $pdf->output();
    }
}
