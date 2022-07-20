<?php

namespace SmoDav\MessageBroker\Workers;

use Carbon\CarbonImmutable;

class TerminatedProcess
{
    public function __construct(public WorkerProcess $workerProcess, public CarbonImmutable $terminatedAt)
    {
    }
}
