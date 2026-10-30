<?php

namespace Laravel\Horizon\Http\Controllers;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Http\Request;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;

class PendingJobsController extends Controller
{
    /**
     * The job repository implementation.
     *
     * @var \Laravel\Horizon\Contracts\JobRepository
     */
    public $jobs;

    /**
     * The queue factory implementation.
     *
     * @var \Illuminate\Contracts\Queue\Factory
     */
    public $queue;

    /**
     * The supervisor repository implementation.
     *
     * @var \Laravel\Horizon\Contracts\SupervisorRepository
     */
    public $supervisors;

    /**
     * Create a new controller instance.
     *
     * @param  \Laravel\Horizon\Contracts\JobRepository  $jobs
     * @param  \Illuminate\Contracts\Queue\Factory  $queue
     * @param  \Laravel\Horizon\Contracts\SupervisorRepository  $supervisors
     * @return void
     */
    public function __construct(JobRepository $jobs, QueueFactory $queue, SupervisorRepository $supervisors)
    {
        parent::__construct();

        $this->jobs = $jobs;
        $this->queue = $queue;
        $this->supervisors = $supervisors;
    }

    /**
     * Get all of the pending jobs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function index(Request $request)
    {
        $jobs = $this->jobs
            ->getPending($request->query('starting_at', -1))
            ->map(function ($job) {
                $job->payload = json_decode($job->payload);

                return $job;
            })
            ->values();

        return [
            'jobs' => $jobs,
            'total' => $this->jobs->countPending(),
            'ready' => $this->countQueued('readyNow'),
            'delayed' => $this->countQueued('delayedNow'),
        ];
    }

    /**
     * Sum the given queue size method over every queue that is being worked by the supervisors.
     *
     * @param  string  $method
     * @return int
     */
    protected function countQueued($method)
    {
        return collect($this->supervisors->all())
            ->flatMap(fn ($supervisor) => array_keys($supervisor->processes))
            ->flatMap(function ($queue) {
                [$connection, $queueNames] = explode(':', $queue, 2);

                return collect(explode(',', $queueNames))->map(fn ($queueName) => $connection.':'.$queueName);
            })
            ->unique()
            ->sum(function ($queue) use ($method) {
                [$connection, $queueName] = explode(':', $queue, 2);

                return $this->queue->connection($connection)->{$method}($queueName);
            });
    }

    /**
     * Decode the given job.
     *
     * @param  object  $job
     * @return object
     */
    protected function decode($job)
    {
        $job->payload = json_decode($job->payload);

        return $job;
    }
}
