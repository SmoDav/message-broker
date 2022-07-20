<?php

namespace SmoDav\MessageBroker\Workers;

use Carbon\CarbonImmutable;
use Closure;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * @mixin Process
 */
class WorkerProcess
{
    /**
     * The output handler callback.
     *
     * @var Closure
     */
    public $output;

    /**
     * The time at which the cooldown period will be over.
     *
     * @var CarbonImmutable|null
     */
    public CarbonImmutable|null $restartAgainAt = null;

    /**
     * The time at which the worker was terminated.
     *
     * @var CarbonImmutable|null
     */
    public CarbonImmutable|null $terminatedAt = null;

    /**
     * @param Process $process
     */
    public function __construct(protected Process $process)
    {
    }

    /**
     * Start the process.
     *
     * @param Closure $callback
     *
     * @return self
     */
    public function start(Closure $callback): self
    {
        $this->output = $callback;

        $this->cooldown();

        $this->process->start($callback);

        return $this;
    }

    /**
     * Pause the worker process.
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    public function pause(): void
    {
        $this->sendSignal(SIGUSR2);
    }

    /**
     * Instruct the worker process to continue working.
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    public function continue(): void
    {
        $this->sendSignal(SIGCONT);
    }

    /**
     * Evaluate the current state of the process.
     *
     * @return void
     */
    public function monitor(): void
    {
        if ($this->process->isRunning() || $this->coolingDown()) {
            return;
        }

        $this->restart();
    }

    /**
     * Restart the process.
     *
     * @return void
     */
    protected function restart(): void
    {
        $this->start($this->output);
    }

    /**
     * Terminate the underlying process.
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    public function terminate(): void
    {
        $this->sendSignal(SIGTERM);
        $this->terminatedAt = CarbonImmutable::now();
    }

    /**
     * Stop the underlying process.
     *
     * @return void
     */
    public function stop(): void
    {
        if ($this->process->isRunning()) {
            $this->process->stop();
        }
    }

    /**
     * Send a POSIX signal to the process.
     *
     * @param int $signal
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    protected function sendSignal($signal): void
    {
        try {
            $this->process->signal($signal);
        } catch (ExceptionInterface $e) {
            if ($this->process->isRunning()) {
                throw $e;
            }
        }
    }

    /**
     * Begin the cool-down period for the process.
     *
     * @return void
     */
    protected function cooldown(): void
    {
        if ($this->coolingDown()) {
            return;
        }

        if ($this->restartAgainAt) {
            $this->restartAgainAt = !$this->process->isRunning()
                ? CarbonImmutable::now()->addMinute()
                : null;
        } else {
            $this->restartAgainAt = CarbonImmutable::now()->addSecond();
        }
    }

    /**
     * Determine if the process is cooling down from a failed restart.
     *
     * @return bool
     */
    public function coolingDown(): bool
    {
        return isset($this->restartAgainAt) && CarbonImmutable::now()->lt($this->restartAgainAt);
    }

    /**
     * Set the output handler.
     *
     * @param Closure $callback
     *
     * @return $this
     */
    public function handleOutputUsing(Closure $callback): self
    {
        $this->output = $callback;

        return $this;
    }

    /**
     * Check if the process is terminated and has hanged.
     *
     * @param int $timeout
     *
     * @return bool
     */
    public function hangedAfterTermination(int $timeout): bool
    {
        return $this->terminatedAt && $this->terminatedAt->addSeconds($timeout)->lte(CarbonImmutable::now());
    }

    /**
     * Pass on method calls to the underlying process.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->process->{$method}(...$parameters);
    }
}
