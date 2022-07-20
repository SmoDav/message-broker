<?php

namespace SmoDav\MessageBroker\Supervisors;

class SupervisorOptions
{
    /**
     * @param string $stream
     * @param string $name
     * @param int    $max
     * @param int    $timeout
     * @param bool   $waitOnTerminate
     * @param int    $sleepWorker
     * @param int    $restartWorker
     */
    public function __construct(
        public string $stream,
        public string $name,
        public int $max = 1,
        public int $timeout = 60,
        public bool $waitOnTerminate = true,
        public int $sleepWorker = 1,
        public int $restartWorker = 60,
    ) {
    }
}
