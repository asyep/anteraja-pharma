<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProofService
{
    // encrypted database storage survives serverless restarts; never expose a storage path.
    public function store(int $shipmentId, UploadedFile $file): string
    {
        $dimensions = getimagesize($file->getRealPath());
        if (!$dimensions || $dimensions[0] * $dimensions[1] > 16000000) {
            throw \Illuminate\Validation\ValidationException::withMessages(['photo_proof' => 'Foto maksimal 16 megapiksel. Gunakan foto yang lebih kecil.']);
        }
        $id = (string) Str::uuid();
        $bytes = file_get_contents($file->getRealPath());
        DB::table('private_assets')->insert([
            'id' => $id, 'shipment_id' => $shipmentId, 'mime' => $file->getMimeType(),
            'sha256' => hash('sha256', $bytes), 'encrypted_content' => Crypt::encryptString(base64_encode($bytes)),
            'created_at' => now(),
        ]);
        return 'db:'.$id;
    }

    public function bytes(int $shipmentId, string $path): array
    {
        if (str_starts_with($path, 'db:')) {
            $asset = DB::table('private_assets')->where('id', substr($path, 3))->where('shipment_id', $shipmentId)->first();
            abort_unless($asset, 404);
            $bytes = base64_decode(Crypt::decryptString($asset->encrypted_content), true);
            abort_unless($bytes !== false && hash_equals($asset->sha256, hash('sha256', $bytes)), 503);
            return [$bytes, $asset->mime];
        }
        abort_unless(Storage::disk('local')->exists($path), 404);
        return [Storage::disk('local')->get($path), Storage::disk('local')->mimeType($path)];
    }

    public function response(int $shipmentId, string $path)
    {
        [$bytes, $mime] = $this->bytes($shipmentId, $path);
        return response($bytes)->header('Content-Type', $mime)->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
