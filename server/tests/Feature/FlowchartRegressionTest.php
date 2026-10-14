<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use App\Models\AiEvaluation;
use App\Services\TelemetryAutomationService;
use App\Services\WorkflowService;
use App\Services\FinalShipmentReportService;
use App\Jobs\SummarizeIncident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlowchartRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $checker;
    private User $courier;
    private User $buyer;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['pharma.telegram_token' => null, 'pharma.openai_api_key' => null, 'pharma.demo_enabled' => true]);
        $this->travelTo(now()->startOfMinute());
        foreach (['admin', 'checker', 'courier', 'buyer'] as $role) $this->$role = User::factory()->create(['role' => $role]);
        $this->shipment = Shipment::create([
            'tracking_number' => 'PHA-REGRESSION', 'created_by' => $this->admin->id, 'buyer_id' => $this->buyer->id, 'inspector_id' => $this->checker->id,
            'sender_name' => 'QA sender', 'recipient_name' => 'QA recipient', 'recipient_phone' => '081200000000', 'address' => 'QA address',
            'origin_city' => 'Jakarta', 'destination_city' => 'Bekasi', 'last_location' => 'Hub QA', 'item_summary' => 'QA specimen', 'handling_instructions' => 'Keep sealed',
            'scheduled_at' => now()->addDay(), 'status' => 'in_transit', 'temperature_profile' => 'controlled', 'temperature_min' => 2, 'temperature_max' => 8, 'is_demo' => true,
        ]);
        $this->shipment->assignments()->create(['courier_id' => $this->courier->id, 'assigned_by' => $this->admin->id, 'status' => 'accepted', 'reason' => 'QA']);
    }

    private function point(float $temperature = 5, ?string $event = null): array
    {
        return ['latitude' => -6.2, 'longitude' => 106.8, 'temperature' => $temperature, 'recorded_at' => now()->toISOString(), 'client_event_id' => $event ?? (string)Str::uuid()];
    }

    public function test_three_minute_excursion_holds_once_and_deduplicates_offline_retry(): void
    {
        $engine = app(TelemetryAutomationService::class);
        $id = (string)Str::uuid();
        $first = $this->point(12.5, $id);
        $engine->record($this->shipment, $this->courier, $first);
        $engine->record($this->shipment, $this->courier, $first);
        $this->assertDatabaseCount('tracking_points', 1);
        $this->travel(1)->minutes(); $engine->record($this->shipment, $this->courier, $this->point(12.5));
        $this->travel(2)->minutes(); $evaluation = $engine->record($this->shipment, $this->courier, $this->point(12.5));
        $this->assertFalse($evaluation->condition_result);
        $this->assertSame('held', $this->shipment->fresh()->status);
        $this->assertDatabaseCount('issues', 1);
        $this->travel(1)->minutes(); $engine->record($this->shipment, $this->courier, $this->point(12.5));
        $this->assertDatabaseCount('issues', 1);
        Http::assertNothingSent();
    }

    public function test_predictive_warning_is_not_a_fake_sensor_or_automatic_hold(): void
    {
        $engine = app(TelemetryAutomationService::class);
        $engine->record($this->shipment, $this->courier, $this->point(6));
        $this->travel(1)->minutes();
        $result = $engine->record($this->shipment, $this->courier, $this->point(7));
        $this->assertEquals(1, $result->anomaly_details['predicted_breach_minutes']);
        $this->assertSame('in_transit', $this->shipment->fresh()->status);
    }

    public function test_gap_breaks_the_temperature_exposure_window(): void
    {
        $engine = app(TelemetryAutomationService::class);
        $engine->record($this->shipment, $this->courier, $this->point(12));
        $this->travel(4)->minutes();
        $result = $engine->record($this->shipment, $this->courier, $this->point(12));
        $this->assertTrue($result->condition_result);
        $this->assertEquals(0, $result->anomaly_details['duration_seconds']);
    }

    public function test_wrong_courier_and_customer_cannot_supply_telemetry(): void
    {
        $other = User::factory()->create(['role' => 'courier']);
        $this->actingAs($other)->postJson('/api/shipments/'.$this->shipment->id.'/telemetry', $this->point())->assertNotFound();
        $this->actingAs($this->buyer)->postJson('/api/shipments/'.$this->shipment->id.'/telemetry', $this->point())->assertForbidden();
    }

    public function test_simulator_is_admin_only_and_cannot_touch_live_shipments(): void
    {
        $payload = ['scenario' => 'spike', 'event_id' => (string)Str::uuid(), 'temperature' => 12.5];
        $this->actingAs($this->buyer)->postJson('/api/demo/shipments/'.$this->shipment->id, $payload)->assertForbidden();
        $this->shipment->update(['is_demo' => false]);
        $this->actingAs($this->admin)->postJson('/api/demo/shipments/'.$this->shipment->id, $payload)->assertUnprocessable();
        config(['pharma.demo_enabled' => false]);
        $this->actingAs($this->admin)->getJson('/api/demo/shipments')->assertForbidden();
    }

    public function test_demo_spike_creates_real_hold_and_retry_is_idempotent(): void
    {
        $payload = ['scenario' => 'spike', 'event_id' => (string)Str::uuid(), 'temperature' => 12.5];
        $path = '/api/demo/shipments/'.$this->shipment->id;
        $this->actingAs($this->admin)->postJson($path, $payload)->assertOk();
        $this->actingAs($this->admin)->postJson($path, $payload)->assertOk();
        $this->assertSame('held', $this->shipment->fresh()->status);
        $this->assertDatabaseCount('tracking_points', 4);
        $this->assertDatabaseCount('issues', 1);
        $this->assertDatabaseHas('tracking_points', ['simulated' => true]);
    }

    public function test_temperature_resume_requires_fresh_normal_reading(): void
    {
        $this->shipment->update(['status' => 'held']);
        $this->shipment->issues()->create(['user_id' => $this->admin->id, 'type' => 'temperature', 'description' => 'QA deviation', 'previous_status' => 'in_transit']);
        $data = ['version' => $this->shipment->version, 'reason' => 'Verify recovery first', 'decision' => 'resume'];
        $this->actingAs($this->admin)->postJson('/api/shipments/'.$this->shipment->id.'/actions/decide', $data)->assertUnprocessable();
        app(TelemetryAutomationService::class)->record($this->shipment, $this->courier, $this->point());
        $this->actingAs($this->admin)->postJson('/api/shipments/'.$this->shipment->id.'/actions/decide', $data)->assertOk();
        $this->assertSame('in_transit', $this->shipment->fresh()->status);
    }

    public function test_pdf_and_pod_are_private_and_closed_records_cannot_be_changed(): void
    {
        app(TelemetryAutomationService::class)->record($this->shipment, $this->courier, $this->point());
        $this->shipment->update(['status' => 'arrived']);
        $s = app(WorkflowService::class)->apply($this->shipment->id, $this->courier, 'deliver', [
            'version' => $this->shipment->version, 'reason' => 'Delivered intact', 'received_by' => 'QA receiver', 'receiver_signature' => 'QA receiver',
            'photo_proof' => UploadedFile::fake()->image('pod.jpg'),
        ]);
        $report = $s->finalReport()->firstOrFail();
        $this->assertSame($report->id, app(FinalShipmentReportService::class)->publish($s)->id);
        foreach ([$this->admin, $this->courier, $this->buyer] as $user) {
            $response = $this->actingAs($user)->get('/api/shipments/'.$s->id.'/report.pdf');
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
            $this->assertSame($report->sha256, hash('sha256', $response->getContent()));
        }
        $this->actingAs($this->checker)->get('/api/shipments/'.$s->id.'/report.pdf')->assertForbidden();
        $other = User::factory()->create(['role' => 'buyer']);
        $this->actingAs($other)->getJson('/api/shipments/'.$s->id.'/pod')->assertNotFound();
        $this->actingAs($this->courier)->postJson('/api/shipments/'.$s->id.'/telemetry', $this->point())->assertUnprocessable();
        $this->getJson('/api/report-verification/'.$report->verification_token)->assertOk()->assertJsonMissingPath('recipient_name')->assertJsonPath('sha256', $report->sha256);
    }

    public function test_optional_ai_is_queued_and_never_changes_the_safety_result(): void
    {
        Queue::fake(); config(['pharma.openai_api_key' => 'test-only-placeholder']);
        $engine = app(TelemetryAutomationService::class);
        $engine->record($this->shipment, $this->courier, $this->point(12));
        $this->travel(1)->minutes(); $engine->record($this->shipment, $this->courier, $this->point(12));
        $this->travel(2)->minutes(); $evaluation = $engine->record($this->shipment, $this->courier, $this->point(12));
        Queue::assertPushed(SummarizeIncident::class);
        Http::assertNothingSent();
        Http::fake(['api.openai.com/*' => Http::response([], 429)]);
        (new SummarizeIncident($evaluation->id))->handle();
        $this->assertSame('unavailable', $evaluation->fresh()->anomaly_details['assistant_status']);
        $this->assertFalse($evaluation->fresh()->condition_result);
        $this->assertSame('held', $this->shipment->fresh()->status);
    }

    public function test_assign_requires_active_courier_with_sufficient_cold_box(): void
    {
        $this->shipment->update(['status' => 'approved', 'weight_kg' => 12]);
        $path = '/api/shipments/'.$this->shipment->id.'/actions/assign';
        $data = ['version' => 1, 'reason' => 'Choose suitable cold-box', 'courier_id' => $this->courier->id];
        $this->courier->update(['is_active' => false, 'cold_box_capacity_kg' => 20]);
        $this->actingAs($this->admin)->postJson($path, $data)->assertUnprocessable();
        $this->courier->update(['is_active' => true, 'cold_box_capacity_kg' => 5]);
        $this->actingAs($this->admin)->postJson($path, $data)->assertUnprocessable();
        $this->courier->update(['cold_box_capacity_kg' => 20]);
        $this->actingAs($this->admin)->postJson($path, $data)->assertOk();
    }

    public function test_simulator_remains_blocked_in_production_even_when_flag_is_true(): void
    {
        app()->instance('env', 'production');
        $this->actingAs($this->admin)->getJson('/api/demo/shipments')->assertForbidden();
    }

    public function test_resuming_one_issue_never_hides_a_second_open_issue(): void
    {
        $this->shipment->update(['status' => 'held']);
        foreach (['signal_loss', 'temperature'] as $type) $this->shipment->issues()->create(['user_id' => $this->admin->id, 'type' => $type, 'description' => 'QA incident', 'previous_status' => 'in_transit']);
        app(TelemetryAutomationService::class)->record($this->shipment, $this->courier, $this->point());
        $path = '/api/shipments/'.$this->shipment->id.'/actions/decide';
        $this->actingAs($this->admin)->postJson($path, ['version' => 1, 'reason' => 'Verified recent sample', 'decision' => 'resume'])->assertOk();
        $this->assertSame('held', $this->shipment->fresh()->status);
        $this->actingAs($this->admin)->postJson($path, ['version' => 2, 'reason' => 'Verified second incident', 'decision' => 'resume'])->assertOk();
        $this->assertSame('in_transit', $this->shipment->fresh()->status);
    }
}
