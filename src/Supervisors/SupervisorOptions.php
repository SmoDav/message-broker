<?php

namespace SmoDav\MessageBroker\Supervisors;

use InvalidArgumentException;

class SupervisorOptions
{
    /**
     * @param string    $stream
     * @param string    $name
     * @param int       $max
     * @param int       $timeout
     * @param bool      $waitOnTerminate
     * @param int|float $sleepWorker
     * @param int       $restartWorker
     */
    public function __construct(
        public string $stream,
        public string $name,
        public int $max = 1,
        public int $timeout = 60,
        public bool $waitOnTerminate = true,
        public int|float $sleepWorker = 1,
        public int $restartWorker = 60,
    ) {
    }

    /**
     * Create new instance from JSON string.
     *
     * @param string $config
     *
     * @return static
     */
    public static function fromJson(string $config): self
    {
        $config = json_decode($config);

        if (!isset($config->stream, $config->name, $config->max, $config->timeout)) {
            throw new InvalidArgumentException('The provided config is invalid.');
        }

        return new static($config->stream, $config->name, $config->max, $config->timeout);
    }
}
