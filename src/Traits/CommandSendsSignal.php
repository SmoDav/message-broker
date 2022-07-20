<?php

namespace SmoDav\MessageBroker\Traits;

use Illuminate\Support\Facades\Cache;
use SmoDav\MessageBroker\Supervisors\Supervisor;

trait CommandSendsSignal
{
    /**
     * Send the given signal to the process if it exists.
     *
     * @param int    $signal
     * @param string $message
     *
     * @return void
     */
    protected function sendSignal(int $signal, string $message): void
    {
        /** @var string $stream */
        $stream = $this->argument('stream');
        /** @var string $group */
        $group = $this->argument('group');

        $key = Supervisor::getCacheKeyByName($stream, $group);

        /** @var string|null $processId */
        $processId = Cache::get($key);

        if ($processId) {
            posix_kill((int) $processId, $signal);

            $this->info("{$message} with PID {$processId}");
        }
    }
}
