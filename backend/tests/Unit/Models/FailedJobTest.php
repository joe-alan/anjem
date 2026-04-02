<?php

namespace Tests\Unit\Models;

use App\Models\FailedJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailedJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeJob(array $payloadData = [], array $overrides = []): FailedJob
    {
        $payload = array_merge(['data' => []], $payloadData);

        return FailedJob::create(array_merge([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode($payload),
            'exception' => 'RuntimeException: something failed',
            'failed_at' => now(),
        ], $overrides));
    }

    public function test_job_name_returns_short_class_name(): void
    {
        $job = $this->makeJob([
            'data' => ['commandName' => 'App\\Jobs\\SendWelcomeEmail'],
        ]);

        $this->assertEquals('SendWelcomeEmail', $job->job_name);
    }

    public function test_job_name_strips_deep_namespace(): void
    {
        $job = $this->makeJob([
            'data' => ['commandName' => 'App\\Jobs\\Notifications\\SendPushNotification'],
        ]);

        $this->assertEquals('SendPushNotification', $job->job_name);
    }

    public function test_job_name_returns_unknown_when_command_name_missing(): void
    {
        $job = $this->makeJob(['data' => []]);

        $this->assertEquals('Unknown', $job->job_name);
    }

    public function test_job_name_returns_unknown_when_payload_empty(): void
    {
        $job = $this->makeJob([]);

        $this->assertEquals('Unknown', $job->job_name);
    }

    public function test_failed_at_is_cast_to_datetime(): void
    {
        $job = $this->makeJob();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->failed_at);
    }

    public function test_model_has_no_timestamps(): void
    {
        $job = new FailedJob;

        $this->assertFalse($job->timestamps);
    }

    public function test_table_name_is_failed_jobs(): void
    {
        $this->assertEquals('failed_jobs', (new FailedJob)->getTable());
    }
}
