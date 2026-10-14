<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained();
            $table->foreignId('courier_id')->constrained('users');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kmh', 6, 2)->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['shipment_id', 'recorded_at']);
        });

        Schema::create('ai_evaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('evaluation_id')->unique();
            $table->foreignId('shipment_id')->constrained();
            $table->foreignId('tracking_point_id')->nullable()->constrained();
            $table->boolean('condition_result');
            $table->string('final_ai_status');
            $table->string('incident_type')->nullable();
            $table->json('anomaly_details')->nullable();
            $table->timestamp('evaluation_time');
            $table->timestamps();
            $table->index(['shipment_id', 'evaluation_time']);
        });

        Schema::create('shipment_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('report_id')->unique();
            $table->foreignId('shipment_id')->unique()->constrained();
            $table->string('final_status');
            $table->json('delivered_to');
            $table->json('snapshot');
            $table->timestamp('completed_at');
            $table->timestamps();
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->string('photo_proof_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_reports');
        Schema::dropIfExists('ai_evaluations');
        Schema::dropIfExists('tracking_points');
        Schema::table('inspections', fn (Blueprint $table) => $table->dropColumn('photo_proof_path'));
    }
};
