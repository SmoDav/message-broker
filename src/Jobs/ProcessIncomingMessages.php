<?php

namespace SmoDav\MessageBroker\Jobs;

use Exception;
use Illuminate\Support\Facades\Redis;
use SmoDav\MessageBroker\Contracts\Job;
use SmoDav\MessageBroker\Events\MessageProcessingFailed;
use SmoDav\MessageBroker\Events\MessageReceived;
use SmoDav\MessageBroker\Message;
use SmoDav\MessageBroker\Workers\WorkerOptions;

class ProcessIncomingMessages implements Job
{
    protected string $redisKey;

    protected array $message = [];

    /**
     * @param WorkerOptions $options
     * @param string        $stream
     * @param string        $group
     * @param string        $consumer
     * @param int           $maxAttempts
     */
    public function __construct(
        protected WorkerOptions $options,
        protected string $stream,
        protected string $group,
        protected string $consumer,
        protected int $maxAttempts = 3
    ) {
        $this->redisKey = config('database.redis.options.prefix') . $this->stream;
    }

    /**
     * Create a new group.
     *
     * @param string $stream
     * @param string $group
     *
     * @return void
     */
    public static function createGroup(string $stream, string $group): void
    {
        Redis::command('xGroup', ['CREATE', $stream, $group, 0, true]);
    }

    /**
     * Check if the job is ready and can be handled.
     *
     * @return bool
     */
    public function ready(): bool
    {
        return !empty($this->message);
    }

    /**
     * Prepare the job for execution.
     *
     * @return void
     */
    public function prepare(): void
    {
        if ($message = $this->getPendingMessage()) {
            $this->message = $message;

            return;
        }

        ini_set('default_socket_timeout', -1);

        $input = Redis::command('xReadGroup', [$this->group, $this->consumer, [$this->stream => '>'], 1, 1000]);

        if (!empty($input)) {
            $this->message = $input[$this->redisKey];
        }
    }

    /**
     * Get any pending message.
     *
     * @return array|null
     */
    protected function getPendingMessage(): array|null
    {
        if (!$this->options->pending) {
            return null;
        }

        $input = Redis::command('xPending', [$this->stream, $this->group, '-', '+', 1]);

        if (empty($input)) {
            return null;
        }

        if ($input[0][2] < ($this->options->timeout * 1000)) {
            return null;
        }

        /** @var string $streamId */
        $streamId = $input[0][0];
        $attempts = $input[0][3];

        /** @var array<string ,string> $input */
        $input = Redis::command('xClaim', [$this->stream, $this->group, $this->consumer, 0, [$streamId]]);

        if ($attempts > $this->maxAttempts) {
            $this->markAsFailed($input);
            $this->acknowledge([$streamId]);

            return null;
        }

        return $input;
    }

    /**
     * Execute the job.
     *
     * @throws Exception
     *
     * @return void
     */
    public function handle(): void
    {
        if (!empty($this->message)) {
            $this->processMessage();
        }
    }

    /**
     * Process the input message from the stream.
     *
     * @throws Exception
     *
     * @return void
     */
    protected function processMessage(): void
    {
        /** @var string $streamId */
        $streamId = array_key_first($this->message);

        if ($streamId) {
            /** @var array<string, string> $item */
            $item = $this->message[$streamId];

            $this->evaluatePayload($item);

            $this->acknowledge([$streamId]);
        }
    }

    /**
     * Mark the message as failed.
     *
     * @param array $input
     *
     * @return void
     */
    protected function markAsFailed(array $input): void
    {
        MessageProcessingFailed::dispatch(json_encode($input));
    }

    /**
     * Acknowledge and delete the payload.
     *
     * @param array<array-key, string> $streamIds
     *
     * @return void
     */
    protected function acknowledge(array $streamIds): void
    {
        Redis::command('xAck', [$this->stream, $this->group, $streamIds]);

        Redis::command('xDel', [$this->stream, $streamIds]);
    }

    /**
     * Evaluate the payload and dispatch it.
     *
     * @param array<string, string> $payload
     *
     * @throws Exception
     *
     * @return void
     */
    protected function evaluatePayload(array $payload): void
    {
        $message = Message::fromArray($payload);

        MessageReceived::dispatch($message);
    }
}
