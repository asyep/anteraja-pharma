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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PharmaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $courier;

    private User $buyer;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['pharma.telegram_token' => null]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->courier = User::factory()->create(['role' => 'courier']);
        $this->buyer = User::factory()->create(['role' => 'buyer']);
        $this->shipment = Shipment::create(['tracking_number' => 'PHA-TEST-001', 'buyer_id' => $this->buyer->id, 'inspector_id' => $this->courier->id, 'created_by' => $this->admin->id,
            'sender_name' => 'Pengirim contoh', 'recipient_name' => 'Penerima contoh', 'recipient_phone' => '081200000000', 'address' => 'Alamat contoh',
            'origin_city' => 'Jakarta', 'destination_city' => 'Bekasi', 'item_summary' => 'Perlengkapan kesehatan', 'package_count' => 1,
            'handling_instructions' => 'Jaga segel tetap utuh', 'temperature_profile' => 'ambient', 'scheduled_at' => now()->addDay(), 'status' => 'inspection', 'last_location' => 'Hub Jakarta']);
        app(WorkflowService::class)->event($this->shipment, $this->admin, 'created', 'Kiriman dibuat', 'Pemeriksaan wajib belum selesai.');
    }

    private function action(User $user, string $action, array $extra = [], int $expected = 200): void
    {
        $this->shipment->refresh();
        $this->actingAs($user)->postJson('/api/shipments/'.$this->shipment->id.'/actions/'.$action, ['version' => $this->shipment->version, 'reason' => 'Catatan pemeriksaan yang jelas.'] + $extra)->assertStatus($expected);
        $this->shipment->refresh();
    }

    private function approveAndAssign(bool $depart = true): void
    {
        $this->action($this->courier, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
        $this->action($this->admin, 'approve');
        $this->action($this->admin, 'assign', ['courier_id' => $this->courier->id]);
        $this->action($this->courier, 'accept');
        if ($depart) {
            $this->action($this->courier, 'depart', ['location' => 'Jalan menuju Bekasi']);
        }
    }

    public function test_required_pharma_demo_incomplete_inspection_block_approve_seal_issue_return(): void
    {
        $this->action($this->courier, 'inspect', ['packaging' => true, 'seal' => false, 'label' => true]);
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
    }

    public function test_buyer_isolation_and_server_roles(): void
    {
        $other = User::factory()->create(['role' => 'buyer']);
        $this->actingAs($other)->getJson('/api/shipments/'.$this->shipment->id)->assertNotFound();
        $this->actingAs($other)->getJson('/api/shipments')->assertJsonPath('total', 0);
        $this->action($this->buyer, 'approve', [], 403);
        $this->action($this->courier, 'assign', ['courier_id' => $this->courier->id], 403);
        $this->actingAs($this->buyer)->getJson('/api/reports')->assertForbidden();
        $this->actingAs($this->courier)->postJson('/api/shipments', [])->assertForbidden();
    }

    public function test_no_session_cannot_access_shipments(): void
    {
        $this->getJson('/api/shipments')->assertUnauthorized();
    }

    public function test_stale_version_does_not_duplicate_decisions(): void
    {
        $this->action($this->courier, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
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
        $this->action($this->courier, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true], 422);
        $this->action($this->courier, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true, 'temperature' => 15]);
        $this->action($this->admin, 'approve', [], 422);
        $this->action($this->courier, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true, 'temperature' => 5]);
        $this->action($this->admin, 'approve');
        $this->assertSame('approved', $this->shipment->status);
    }

    public function test_missing_fields_validated_instead_of_server_crash(): void
    {
        $this->action($this->courier, 'inspect', ['packaging' => true], 422);
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
        $this->action($this->courier, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
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
        $this->action($this->courier, 'inspect', ['packaging' => true, 'seal' => true, 'label' => true]);
        $this->action($this->admin, 'approve');
        $this->assertSame('approved', $this->shipment->status);
    }

    public function test_new_events_create_new_report_revision_without_overwriting_old_evidence(): void
    {
        $date = now('Asia/Jakarta')->toDateString();
        $old = app(ReportService::class)->generate($date);
        $this->action($this->courier,'inspect',['packaging' => true, 'seal' => true, 'label' => true]);
        $new = app(ReportService::class)->generate($date);
        $this->assertSame(2,$new->revision);
        $this->assertSame('inspection',$old->fresh()->shipment_snapshot[0]['status']);
        $this->assertSame('pending_approval',$new->shipment_snapshot[0]['status']);
        $this->assertSame($new->id,app(ReportService::class)->generate($date)->id);
    }
}
