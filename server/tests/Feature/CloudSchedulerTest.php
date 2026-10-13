<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloudSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_rejects_missing_and_incorrect_credentials(): void
    {
        config(['pharma.cron_secret' => 'test-only-cron-secret']);
        $this->getJson('/api/cron')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer wrong')->getJson('/api/cron')->assertUnauthorized();
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_authorized_daily_scheduler_creates_one_report(): void
    {
        config(['pharma.cron_secret' => 'test-only-cron-secret', 'cache.default' => 'array']);
        $this->withHeader('Authorization', 'Bearer test-only-cron-secret')->getJson('/api/cron?mode=daily')->assertOk()->assertJsonStructure(['report_id', 'reminders', 'messages']);
        $this->withHeader('Authorization', 'Bearer test-only-cron-secret')->getJson('/api/cron?mode=daily')->assertOk();
        $this->assertDatabaseCount('reports', 1);
    }
}
