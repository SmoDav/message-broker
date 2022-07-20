<?php

namespace SmoDav\MessageBroker;

use Illuminate\Support\Facades\Redis;
use SmoDav\MessageBroker\Enums\MessageType;

class Message
{
    /**
     * Create a new message instance.
     *
     * @param MessageType          $type
     * @param array<string, mixed> $payload
     */
    final public function __construct(public MessageType $type, public array $payload)
    {
    }

    /**
     * Create a new instance from the JSON string.
     *
     * @param array<string, string> $input
     *
     * @return static
     */
    public static function fromArray(array $input): static
    {
        /** @var string $type */
        $type = $input['type'];

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($input['payload']);

        return new static(MessageType::from((int) $type), $payload);
    }

    /**
     * Broadcast the message to the broker.
     *
     * @param string $stream
     *
     * @return string
     */
    public function broadcast(string $stream = 'microservices'): string
    {
        return Redis::command('xAdd', [$stream, '*', $this->toArray()]);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    protected function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'payload' => json_encode($this->payload),
        ];
    }
}
