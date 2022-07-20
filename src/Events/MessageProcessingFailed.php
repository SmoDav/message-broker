<?php

namespace SmoDav\MessageBroker\Events;

use Illuminate\Foundation\Events\Dispatchable;

class MessageProcessingFailed
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param string $message
     */
    public function __construct(public string $message)
    {
    }
}
