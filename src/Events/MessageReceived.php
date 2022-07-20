<?php

namespace SmoDav\MessageBroker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use SmoDav\MessageBroker\Message;

class MessageReceived
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param Message $message
     */
    public function __construct(public Message $message)
    {
    }
}
