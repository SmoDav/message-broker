<?php

namespace SmoDav\MessageBroker\Workers;

use InvalidArgumentException;

class WorkerOptions
{
    /**
     * @param string $stream
     * @param string $group
     * @param string $name
     * @param int    $timeout
     * @param bool   $pending
     * @param int    $sleep
     * @param int    $restart
     */
    public function __construct(
        public string $stream,
        public string $group,
        public string $name,
        public int $timeout = 60,
        public bool $pending = false,
        public int $sleep = 3,
        public int $restart = 60,
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

        if (!isset($config->name, $config->pending, $config->timeout)) {
            throw new InvalidArgumentException('The provided config is invalid.');
        }

        return new static($config->name, $config->timeout, $config->pending);
    }

    /**
     * Convert the options to a command string.
     *
     * @return string
     */
    public function toCommandString(): string
    {
        return sprintf(
            '%s %s %s --pending=%s --timeout=%d --sleep=%d --restart=%d',
            $this->stream,
            $this->group,
            $this->name,
            $this->pending ? 'true' : 'false',
            $this->timeout,
            $this->sleep,
            $this->restart
        );
    }

    /**
     * convert to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'pending' => $this->pending,
            'timeout' => $this->timeout,
            'sleep' => $this->sleep,
            'restart' => $this->restart,
        ];
    }

    /**
     * Convert to JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Clone the worker options.
     *
     * @return $this
     */
    public function clone(): self
    {
        return clone $this;
    }
}
