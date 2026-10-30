<?php

namespace Laravel\Horizon\Tests\Controller;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Tests\ControllerTest;
use Mockery;

class PendingJobsControllerTest extends ControllerTest
{
    public function test_pending_jobs_are_returned_with_their_totals()
    {
        $jobs = Mockery::mock(JobRepository::class);
        $jobs->shouldReceive('getPending')->with(-1)->andReturn(collect([
            (object) ['id' => 'first-job', 'payload' => '{"displayName":"App\\\\Jobs\\\\First"}'],
            (object) ['id' => 'second-job', 'payload' => '{"displayName":"App\\\\Jobs\\\\Second"}'],
        ]));
        $jobs->shouldReceive('countPending')->andReturn(42);
        $this->app->instance(JobRepository::class, $jobs);

        // Two supervisors sharing a queue, one of them working a comma separated list of queues...
        $supervisors = Mockery::mock(SupervisorRepository::class);
        $supervisors->shouldReceive('all')->andReturn([
            (object) ['processes' => ['redis:first' => 5]],
            (object) ['processes' => ['redis:first,second' => 5]],
        ]);
        $this->app->instance(SupervisorRepository::class, $supervisors);

        // Each queue must only be counted once...
        $queue = Mockery::mock(QueueFactory::class);
        $queue->shouldReceive('connection')->with('redis')->andReturnSelf();
        $queue->shouldReceive('readyNow')->once()->with('first')->andReturn(10);
        $queue->shouldReceive('readyNow')->once()->with('second')->andReturn(20);
        $queue->shouldReceive('delayedNow')->once()->with('first')->andReturn(3);
        $queue->shouldReceive('delayedNow')->once()->with('second')->andReturn(4);
        $this->app->instance(QueueFactory::class, $queue);

        $response = $this->actingAs(new Fakes\User)
                    ->get('/horizon/api/jobs/pending');

        $response->assertJson([
            'jobs' => [
                ['id' => 'first-job', 'payload' => ['displayName' => 'App\Jobs\First']],
                ['id' => 'second-job', 'payload' => ['displayName' => 'App\Jobs\Second']],
            ],
            'total' => 42,
            'ready' => 30,
            'delayed' => 7,
        ]);
    }

    public function test_delayed_count_is_zero_without_running_supervisors()
    {
        $jobs = Mockery::mock(JobRepository::class);
        $jobs->shouldReceive('getPending')->andReturn(collect());
        $jobs->shouldReceive('countPending')->andReturn(0);
        $this->app->instance(JobRepository::class, $jobs);

        $supervisors = Mockery::mock(SupervisorRepository::class);
        $supervisors->shouldReceive('all')->andReturn([]);
        $this->app->instance(SupervisorRepository::class, $supervisors);

        $queue = Mockery::mock(QueueFactory::class);
        $queue->shouldNotReceive('connection');
        $this->app->instance(QueueFactory::class, $queue);

        $response = $this->actingAs(new Fakes\User)
                    ->get('/horizon/api/jobs/pending');

        $response->assertJson(['jobs' => [], 'total' => 0, 'ready' => 0, 'delayed' => 0]);
    }
}
