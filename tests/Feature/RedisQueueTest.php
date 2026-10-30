<?php

namespace Laravel\Horizon\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Tests\IntegrationTest;

class RedisQueueTest extends IntegrationTest
{
    public function test_ready_and_delayed_jobs_are_counted_separately()
    {
        Queue::push(new Jobs\BasicJob);
        Queue::later(60, new Jobs\BasicJob);
        Queue::later(120, new Jobs\BasicJob);
        Queue::later(60, new Jobs\BasicJob, '', 'other');

        $this->assertSame(1, Queue::connection()->readyNow('default'));
        $this->assertSame(2, Queue::connection()->delayedNow('default'));
        $this->assertSame(0, Queue::connection()->readyNow('other'));
        $this->assertSame(1, Queue::connection()->delayedNow('other'));
        $this->assertSame(0, Queue::connection()->delayedNow('empty'));
    }
}
