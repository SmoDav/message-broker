<?php

namespace SmoDav\MessageBroker\Supervisors;

use Closure;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Cache;
use SmoDav\MessageBroker\Traits\ListensForSignals;
use SmoDav\MessageBroker\Workers\WorkerPool;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Throwable;

class Supervisor
{
    use ListensForSignals;

    /** @var bool */
    protected bool $working = true;

    /** @var WorkerPool */
    protected WorkerPool $pool;

    /**
     * @param SupervisorOptions $options
     * @param Closure           $output
     * @param string            $directory
     */
    public function __construct(protected SupervisorOptions $options, public Closure $output, string $directory)
    {
        $this->pool = new WorkerPool($this->options, $directory, $this->output);
    }

    /**
     * Clean up after the supervisor.
     */
    public function __destruct()
    {
        $this->terminate();
    }

    /**
     * Get the cache key for the given name.
     *
     * @param string $stream
     * @param string $name
     *
     * @return string
     */
    public static function getCacheKeyByName(string $stream, string $name): string
    {
        return "{$stream}:{$name}:supervisor";
    }

    /**
     * Get the cache key.
     *
     * @return string
     */
    public function cacheKey(): string
    {
        return self::getCacheKeyByName($this->options->stream, $this->options->name);
    }

    /**
     * Handle the continue signal.
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    protected function continue(): void
    {
        $this->working = true;

        $this->pool->continue();
    }

    /**
     * Handle the pause signal.
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    protected function pause(): void
    {
        $this->working = false;

        $this->pool->pause();
    }

    /**
     * Handle the restart signal.
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    protected function restart(): void
    {
        $this->working = true;

        $this->pool->restart();
    }

    /**
     * Handle the terminate signal.
     *
     * @param int $status
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    public function terminate(int $status = 0): void
    {
        $this->working = false;

        $this->pool->scale(0);

        if ($this->options->waitOnTerminate) {
            while ($this->pool->pruneTerminatingProcesses()->isNotEmpty()) {
                sleep(1);
            }
        }

        Cache::forget($this->cacheKey());

        exit($status);
    }

    /**
     * Monitor the worker processes.
     *
     * @return void
     */
    public function monitor(): void
    {
        $this->ensureNoDuplicateSupervisors();

        $this->listenForSignals();

        $this->persist();

        /** @phpstan-ignore-next-line  */
        while (true) {
            sleep(1);

            $this->loop();
        }
    }

    /**
     * Ensure no other supervisors are running with the same name.
     *
     * @throws Exception
     *
     * @return void
     */
    public function ensureNoDuplicateSupervisors(): void
    {
        if (Cache::has($this->cacheKey())) {
            throw new Exception('A supervisor is already running');
        }

        Cache::put($this->cacheKey(), getmypid());
    }

    /**
     * Perform a monitor loop.
     *
     * @throws Throwable
     *
     * @return void
     */
    public function loop(): void
    {
        try {
            $this->processPendingSignals();

            $this->processPendingCommands();

            // If the supervisor is working, we will perform any needed scaling operations and
            // monitor all of these underlying worker processes to make sure they are still
            // processing queued jobs. If they have died, we will restart them each here.
            if ($this->working) {
                $this->autoScale();

                $this->pool->monitor();
            }

            // Next, we'll persist the supervisor state to storage so that it can be read by a
            // user interface. This contains information on the specific options for it and
            // the current number of worker processes per queue for easy load monitoring.
            $this->persist();
        } catch (Throwable $e) {
            /** @var ExceptionHandler $handler */
            $handler = app(ExceptionHandler::class);
            $handler->report($e);
        }
    }

    /**
     * Handle any pending commands for the supervisor.
     *
     * @return void
     */
    protected function processPendingCommands(): void
    {
    }

    /**
     * Run the auto-scaling routine for the supervisor.
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    protected function autoScale(): void
    {
        $this->pool->scale($this->options->max);
    }

    protected function persist(): void
    {
    }
}
