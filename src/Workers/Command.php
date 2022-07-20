<?php

namespace SmoDav\MessageBroker\Workers;

use SmoDav\MessageBroker\PhpBinary;

class Command
{
    /**
     * The command to be executed.
     *
     * @var string
     */
    protected static $command = 'exec @php artisan message-broker:worker';

    /**
     * @param WorkerOptions $options
     */
    public function __construct(protected WorkerOptions $options)
    {
    }

    /**
     * Convert the command to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        $command = str_replace('@php', PhpBinary::path(), static::$command);

        return "{$command} {$this->options->toCommandString()}";
    }
}
