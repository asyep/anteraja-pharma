<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\OutboundMessage;
use App\Models\Shipment;
use App\Models\User;
use App\Services\AutomationService;
use App\Services\MessageService;
use App\Services\ReportService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PharmaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $courier;

    private User $checker;

    private User $buyer;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        config(['pharma.telegram_token' => null, 'pharma.openai_api_key' => null]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->checker = User::factory()->create(['role' => 'checker']);
        $this->courier = User::factory()->create(['role' => 'courier']);
        $this->buyer = User::factory()->create(['role' => 'buyer']);
        $this->shipment = Shipment::create(['tracking_number' => 'PHA-TEST-001', 'buyer_id' => $this->buyer->id, 'inspector_id' => $this->checker->id, 'created_by' => $this->admin->id,
            'sender_name' => 'Pengirim contoh', 'recipient_name' => 'Penerima contoh', 'recipient_phone' => '081200000000', 'address' => 'Alamat contoh',
            'origin_city' => 'Jakarta', 'destination_city' => 'Bekasi', 'item_summary' => 'Perlengkapan kesehatan', 'package_count' => 1,
            'handling_instructions' => 'Jaga segel tetap utuh', 'temperature_profile' => 'ambient', 'scheduled_at' => now()->addDay(), 'status' => 'inspection', 'last_location' => 'Hub Jakarta']);
        app(WorkflowService::class)->event($this->shipment, $this->admin, 'created', 'Kiriman dibuat', 'Pemeriksaan wajib belum selesai.');
    }

    private function action(User $user, string $action, array $extra = [], int $expected = 200): void
    {
        $this->shipment->refresh();
        if ($action === 'inspect' && ! array_key_exists('photo_proof', $extra)) {
            $extra['photo_proof'] = UploadedFile::fake()->image('proof.jpg');
        }
        $payload = ['version' => $this->shipment->version, 'reason' => 'Catatan pemeriksaan yang jelas.'] + $extra;
        $request = $this->actingAs($user);
        if (isset($payload['photo_proof'])) {
            $request->post('/api/shipments/'.$this->shipment->id.'/actions/'.$action, $payload, ['Accept' => 'application/json'])->assertStatus($expected);
        } else {
            $request->postJson('/api/shipments/'.$this->shipment->id.'/actions/'.$action, $payload)->assertStatus($expected);
        }
        $this->shipment->refresh();
    }

    private function approveAndAssign(bool $depart = true): void
    {
        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
        $this->action($this->admin, 'approve');
        $this->action($this->admin, 'assign', ['courier_id' => $this->courier->id]);
        $this->action($this->courier, 'accept');
        if ($depart) {
            $this->action($this->courier, 'depart', ['location' => 'Jalan menuju Bekasi']);
        }
    }

    public function test_required_pharma_demo_incomplete_inspection_block_approve_seal_issue_return(): void
    {
        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => false, 'label' => true]);
        $this->action($this->admin, 'approve', [], 422);
        $this->action($this->admin, 'assign', ['courier_id' => $this->courier->id], 422);
        $this->assertDatabaseCount('approvals', 0);
        $this->assertDatabaseHas('events', ['type' => 'dispatch_blocked']);
        $this->approveAndAssign();
        $this->action($this->courier, 'issue', ['issue_type' => 'seal']);
        $this->assertSame('held', $this->shipment->status);
        $this->action($this->courier, 'arrive', ['location' => 'Bekasi'], 422);
        $this->action($this->courier, 'decide', ['decision' => 'return'], 403);
        $this->action($this->admin, 'decide', ['decision' => 'return']);
        $this->action($this->courier, 'return_complete', ['location' => 'Hub Jakarta']);
        $this->assertSame('returned', $this->shipment->status);
        $this->assertDatabaseCount('inspections', 2);
        $this->assertDatabaseHas('decisions', ['action' => 'return', 'user_id' => $this->admin->id]);
        $this->assertNotNull($this->shipment->completed_at);
    }

    public function test_normal_delivery_readiness_and_recipient_confirmation(): void
    {
        $this->action($this->buyer, 'ready', ['ready' => true]);
        $this->approveAndAssign();
        $this->action($this->courier, 'deliver', ['received_by' => 'Petugas'], 422);
        $this->action($this->courier, 'arrive', ['location' => 'Apotek tujuan']);
        $this->action($this->courier, 'deliver', ['received_by' => 'Petugas apotek']);
        $this->action($this->buyer, 'confirm');
        $this->action($this->buyer, 'confirm', [], 422);
        $this->assertSame('delivered', $this->shipment->status);
        $this->assertDatabaseHas('shipment_reports', ['shipment_id' => $this->shipment->id, 'final_status' => 'COMPLETED_SAFE']);
    }

    public function test_buyer_isolation_and_server_roles(): void
    {
        $other = User::factory()->create(['role' => 'buyer']);
        $this->actingAs($other)->getJson('/api/shipments/'.$this->shipment->id)->assertNotFound();
        $this->actingAs($other)->getJson('/api/shipments')->assertJsonPath('total', 0);
        $this->action($this->buyer, 'approve', [], 403);
        $this->action($this->courier, 'assign', ['courier_id' => $this->courier->id], 404);
        $this->actingAs($this->buyer)->getJson('/api/reports')->assertForbidden();
        $this->actingAs($this->courier)->postJson('/api/shipments', [])->assertForbidden();
    }

    public function test_no_session_cannot_access_shipments(): void
    {
        $this->getJson('/api/shipments')->assertUnauthorized();
    }

    public function test_stale_version_does_not_duplicate_decisions(): void
    {
        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
        $old = $this->shipment->version;
        $this->action($this->admin, 'approve');
        $this->actingAs($this->admin)->postJson('/api/shipments/'.$this->shipment->id.'/actions/approve', ['version' => $old, 'reason' => 'Persetujuan duplikat'])->assertConflict();
        $this->assertDatabaseCount('approvals', 1);
    }

    public function test_rejection_reassignment_and_unauthorized_courier(): void
    {
        $this->approveAndAssign(false);
        $other = User::factory()->create(['role' => 'courier']);
        $this->action($other, 'depart', ['location' => 'Jakarta'], 404);
        $this->assertSame('accepted', $this->shipment->status);
        $this->shipment->update(['status' => 'approved']);
        $this->action($this->admin, 'assign', ['courier_id' => $other->id]);
        $this->action($other, 'reject');
        $this->assertSame('approved', $this->shipment->status);
        $this->assertDatabaseHas('assignments', ['courier_id' => $other->id, 'status' => 'rejected']);
    }

    public function test_seal_issue_cannot_be_resumed_and_recheck_requires_new_approval(): void
    {
        $this->approveAndAssign();
        $this->action($this->courier, 'issue', ['issue_type' => 'seal']);
        $this->action($this->admin, 'decide', ['decision' => 'resume'], 422);
        $this->action($this->admin, 'decide', ['decision' => 'recheck']);
        $this->action($this->admin, 'assign', ['courier_id' => $this->courier->id], 422);
        $this->approveAndAssign(false);
        $this->assertDatabaseCount('approvals', 2);
    }

    public function test_temperature_outside_configured_range_keeps_dispatch_blocked(): void
    {
        $this->shipment->update(['temperature_profile' => 'controlled', 'temperature_min' => 2, 'temperature_max' => 8]);
        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true], 422);
        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true, 'temperature' => 15]);
        $this->action($this->admin, 'approve', [], 422);
        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true, 'temperature' => 5]);
        $this->action($this->admin, 'approve');
        $this->assertSame('approved', $this->shipment->status);
    }

    public function test_assigned_courier_telemetry_is_recorded_and_temperature_excursion_is_reported(): void
    {
        $this->shipment->update(['temperature_profile' => 'controlled', 'temperature_min' => 2, 'temperature_max' => 8]);
        $this->action($this->checker, 'inspect', [
            'packaging' => true, 'seal' => true, 'label' => true, 'temperature' => 5,
        ]);
        $this->action($this->admin, 'approve');
        $this->action($this->admin, 'assign', ['courier_id' => $this->courier->id]);
        $this->action($this->courier, 'accept');
        $this->action($this->courier, 'depart', ['location' => 'Jalan menuju Bekasi']);

        $sendTelemetry = function () {
            return $this->actingAs($this->courier)->postJson('/api/shipments/'.$this->shipment->id.'/telemetry', [
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'speed_kmh' => 24.5,
                'temperature' => 10,
            ]);
        };
        $sendTelemetry()->assertCreated()->assertJsonPath('final_ai_status', 'aman');
        $this->travel(2)->minutes();
        $sendTelemetry()->assertCreated()->assertJsonPath('final_ai_status', 'aman');
        $this->travel(2)->minutes();
        $sendTelemetry()->assertCreated()->assertJsonPath('final_ai_status', 'aman');
        $this->travel(2)->minutes();
        config(['pharma.openai_api_key' => 'test-key', 'pharma.openai_model' => 'test-model']);
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Suhu berada di luar batas selama lima menit.']]],
            ]),
        ]);
        $response = $sendTelemetry();
        $response->assertCreated()->assertJsonPath('final_ai_status', 'Penyimpangan');

        $this->assertSame('TEMP_EXCURSION', $response->json('incident_type'));
        $this->assertSame('openai', $response->json('anomaly_details.analysis_source'));
        $this->assertSame('Suhu berada di luar batas selama lima menit.', $response->json('anomaly_details.summary'));
        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key'));
        $this->assertDatabaseCount('tracking_points', 4);
        $this->assertDatabaseCount('ai_evaluations', 4);
        $this->assertDatabaseHas('events', ['type' => 'ai_deviation']);
        $this->actingAs($this->buyer)->getJson('/api/shipments/'.$this->shipment->id)
            ->assertOk()
            ->assertJsonMissingPath('inspector')
            ->assertJsonMissingPath('inspector_id')
            ->assertJsonCount(4, 'tracking_points');
    }

    public function test_missing_telemetry_is_reported_once_and_cleared_when_gps_returns(): void
    {
        $this->approveAndAssign();
        $this->travel(16)->minutes();
        $automation = app(\App\Services\TelemetryAutomationService::class);
        $this->assertSame(1, $automation->detectTelemetryDisruptions());
        $this->assertSame(0, $automation->detectTelemetryDisruptions());
        $this->actingAs($this->courier)->postJson('/api/shipments/'.$this->shipment->id.'/telemetry', [
            'latitude' => -6.2,
            'longitude' => 106.8,
            'speed_kmh' => 20,
        ])->assertCreated()->assertJsonPath('final_ai_status', 'aman');
        $this->assertDatabaseHas('ai_evaluations', ['incident_type' => 'SIGNAL_LOSS', 'condition_result' => false]);
        $this->assertDatabaseHas('events', ['type' => 'ai_recovered']);
    }

    public function test_checker_can_inspect_but_courier_cannot_inspect_another_role_task(): void
    {
        $this->action($this->courier, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true], 404);
        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
        $this->assertSame('pending_approval', $this->shipment->status);
    }

    public function test_inspection_requires_photo_proof_and_customer_can_view_it(): void
    {
        $this->actingAs($this->checker)->postJson('/api/shipments/'.$this->shipment->id.'/actions/inspect', [
            'version' => $this->shipment->version,
            'reason' => 'Pemeriksaan dilakukan.',
            'packaging' => true,
            'seal' => true,
            'label' => true,
        ])->assertUnprocessable();

        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
        $detail = $this->actingAs($this->buyer)->getJson('/api/shipments/'.$this->shipment->id)->assertOk();
        $photoUrl = $detail->json('inspections.0.photo_proof_url');
        $this->assertNotEmpty($photoUrl);
        $this->actingAs($this->buyer)->get($photoUrl)->assertOk();
    }

    public function test_missing_fields_validated_instead_of_server_crash(): void
    {
        $this->action($this->checker, 'inspect', ['packaging' => true], 422);
        $this->actingAs($this->admin)->postJson('/api/shipments', [])->assertUnprocessable();
        $this->actingAs($this->admin)->getJson('/api/shipments?per_page=1000000')->assertUnprocessable();
    }

    public function test_messages_not_marked_delivered_without_configuration(): void
    {
        $counts = app(MessageService::class)->deliver();
        $this->assertGreaterThan(0, $counts['unconfigured']);
        $this->assertDatabaseMissing('messages', ['status' => 'sent']);
    }

    public function test_provider_confirmation_and_failure_are_recorded(): void
    {
        config(['pharma.telegram_token' => 'test-token']);
        $this->admin->update(['telegram_chat_id' => '12345']);
        Http::fake(['api.telegram.org/*' => Http::sequence()->push(['ok' => true, 'result' => ['message_id' => 123]], 200)->push(['ok' => false], 429)->push(['ok' => false], 403)]);
        app(MessageService::class)->deliver();
        $this->assertDatabaseHas('messages', ['user_id' => $this->admin->id, 'status' => 'sent', 'provider_message_id' => '123']);
        $m = OutboundMessage::where('user_id', $this->admin->id)->first();
        $m->update(['status' => 'pending']);
        app(MessageService::class)->deliver();
        $this->assertSame('retry', $m->fresh()->status);
        $m->refresh()->update(['status' => 'pending', 'next_attempt_at' => null]);
        app(MessageService::class)->deliver();
        $this->assertSame('failed', $m->fresh()->status);
    }

    public function test_reminders_and_daily_reports_are_idempotent(): void
    {
        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
        $this->action($this->admin, 'approve');
        $this->action($this->admin, 'assign', ['courier_id' => $this->courier->id]);
        Assignment::query()->update(['created_at' => now()->subMinutes(20)]);
        $this->assertSame(1, app(AutomationService::class)->reminders());
        $this->assertSame(0, app(AutomationService::class)->reminders());
        $date = now('Asia/Jakarta')->toDateString();
        $first = app(ReportService::class)->generate($date);
        $second = app(ReportService::class)->generate($date);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('reports', 1);
        $this->assertSame(1, $first->summary['total']);
        $this->actingAs($this->admin)->get('/api/reports/'.$first->id.'/export')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_webhook_rejects_unsigned_request_and_accepts_signed_once(): void
    {
        config(['pharma.automation_secret' => 'webhook-test-secret']);
        $payload = ['event_id' => 'test-001', 'type' => 'daily_report', 'date' => now('Asia/Jakarta')->toDateString()];
        $this->postJson('/api/webhooks/automation', $payload)->assertUnauthorized();
        $body = json_encode($payload);
        $stamp = (string) time();
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_PHARMA_TIMESTAMP' => $stamp, 'HTTP_X_PHARMA_SIGNATURE' => hash_hmac('sha256', $stamp.'.'.$body, 'webhook-test-secret')];
        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/api/webhooks/automation', [], [], [], $headers, $body)->assertOk();
        }
        $this->assertDatabaseCount('webhook_receipts', 1);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_admin_hold_needs_new_inspection_before_release(): void
    {
        $this->action($this->admin, 'hold');
        $this->action($this->admin, 'assign', ['courier_id' => $this->courier->id], 422);
        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
        $this->action($this->admin, 'approve');
        $this->assertSame('approved', $this->shipment->status);
    }

    public function test_new_events_create_new_report_revision_without_overwriting_old_evidence(): void
    {
        $date = now('Asia/Jakarta')->toDateString();
        $old = app(ReportService::class)->generate($date);
        $this->action($this->checker, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
        $new = app(ReportService::class)->generate($date);
        $this->assertSame(2, $new->revision);
        $this->assertSame('inspection', $old->fresh()->shipment_snapshot[0]['status']);
        $this->assertSame('pending_approval', $new->shipment_snapshot[0]['status']);
        $this->assertSame($new->id, app(ReportService::class)->generate($date)->id);
    }

    public function test_missing_configuration_does_not_starve_new_messages(): void
    {
        config(['pharma.telegram_token' => 'test-token']);
        OutboundMessage::query()->update(['status' => 'unconfigured']);
        $message = OutboundMessage::create(['user_id' => $this->admin->id, 'audience' => 'admin', 'chat_id' => '12345', 'subject' => 'Pesan baru', 'body' => 'Pembaruan kiriman baru', 'action_url' => 'https://pharma.test/kiriman/1', 'dedupe_key' => 'fresh-message', 'status' => 'pending']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 456]], 200)]);
        app(MessageService::class)->deliver(1);
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertDatabaseCount('messages', 5);
    }
}
