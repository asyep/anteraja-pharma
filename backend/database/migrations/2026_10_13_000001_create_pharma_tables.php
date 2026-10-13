<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('buyer')->index();
            $table->string('telegram_chat_id')->nullable();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->string('tracking_number')->unique();
            $t->foreignId('buyer_id')->constrained('users');
            $t->foreignId('inspector_id')->constrained('users');
            $t->foreignId('created_by')->constrained('users');
            $t->string('sender_name');
            $t->string('sender_chat_id')->nullable();
            $t->string('recipient_name');
            $t->string('recipient_phone');
            $t->text('address');
            $t->string('origin_city');
            $t->string('destination_city');
            $t->string('item_summary');
            $t->unsignedInteger('package_count')->default(1);
            $t->text('handling_instructions');
            $t->string('temperature_profile')->default('ambient');
            $t->decimal('temperature_min', 5, 2)->nullable();
            $t->decimal('temperature_max', 5, 2)->nullable();
            $t->dateTime('scheduled_at');
            $t->string('status')->default('inspection')->index();
            $t->string('last_location');
            $t->unsignedInteger('version')->default(1);
            $t->boolean('recipient_ready')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->timestamps();
            $t->index(['buyer_id', 'status']);
        });
        Schema::create('inspections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->boolean('packaging');
            $t->boolean('seal');
            $t->boolean('label');
            $t->text('note');
            $t->decimal('temperature', 5, 2)->nullable();
            $t->boolean('passed');
            $t->timestamps();
            $t->index(['shipment_id', 'id']);
        });
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('inspection_id')->nullable()->constrained();
            $t->string('decision');
            $t->text('reason');
            $t->timestamps();
        });
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained();
            $t->foreignId('courier_id')->constrained('users');
            $t->foreignId('assigned_by')->constrained('users');
            $t->string('status')->default('pending')->index();
            $t->text('reason')->nullable();
            $t->dateTime('responded_at')->nullable();
            $t->dateTime('reminded_at')->nullable();
            $t->timestamps();
            $t->index(['courier_id', 'status']);
        });
        Schema::create('issues', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('type');
            $t->text('description');
            $t->string('previous_status');
            $t->string('status')->default('open')->index();
            $t->timestamps();
        });
        Schema::create('decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('issue_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('action');
            $t->text('reason');
            $t->timestamps();
        });
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('actor_name');
            $t->string('type');
            $t->string('title');
            $t->text('reason');
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['shipment_id', 'created_at']);
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->nullable()->constrained();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->foreignId('event_id')->nullable()->constrained();
            $t->string('audience');
            $t->string('chat_id')->nullable();
            $t->string('subject');
            $t->text('body');
            $t->string('action_url');
            $t->string('status')->default('pending')->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->string('provider_message_id')->nullable();
            $t->string('last_error')->nullable();
            $t->dateTime('next_attempt_at')->nullable();
            $t->dateTime('sent_at')->nullable();
            $t->string('dedupe_key')->unique();
            $t->timestamps();
        });
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->date('report_date')->unique();
            $t->json('summary');
            $t->json('shipment_snapshot');
            $t->timestamps();
        });
        Schema::create('webhook_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('source');
            $t->string('external_id');
            $t->json('result');
            $t->timestamps();
            $t->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_receipts', 'reports', 'messages', 'events', 'decisions', 'issues', 'assignments', 'approvals', 'inspections', 'shipments'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'telegram_chat_id']));
    }
};
