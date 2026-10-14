<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $t) {
            $t->boolean('is_demo')->default(false)->index();
            $t->string('pharma_category')->default('REGULAR');
            $t->decimal('weight_kg', 8, 2)->default(1);
            $t->timestamp('closed_at')->nullable();
            $t->string('pod_proof_path')->nullable();
            $t->string('received_by')->nullable();
            $t->string('receiver_signature')->nullable();
        });
        Schema::table('tracking_points', function (Blueprint $t) {
            $t->string('client_event_id', 80)->nullable();
            $t->boolean('simulated')->default(false);
            $t->unique(['shipment_id', 'client_event_id']);
        });
        Schema::table('shipment_reports', function (Blueprint $t) {
            $t->longText('pdf_content')->nullable();
            $t->string('sha256', 64)->nullable();
            $t->string('verification_token', 64)->nullable()->unique();
        });
        Schema::create('private_assets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('shipment_id')->constrained();
            $t->string('mime');
            $t->string('sha256', 64);
            $t->longText('encrypted_content');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Evidence migration cannot be rolled back destructively. Restore a verified backup instead.');
    }
};
