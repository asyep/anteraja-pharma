<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $t) {
            $t->dropUnique('reports_report_date_unique');
            $t->unsignedInteger('revision')->default(1);
            $t->unsignedBigInteger('last_event_id')->default(0);
            $t->unique(['report_date', 'revision']);
        });
    }

    public function down(): void
    {
        // Multiple snapshots must not be destroyed to force an old unique constraint.
        throw new RuntimeException('Rollback membutuhkan penanganan snapshot laporan secara eksplisit.');
    }
};
