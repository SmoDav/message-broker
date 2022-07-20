<?php

namespace SmoDav\MessageBroker\Workers;

use Closure;
use Illuminate\Support\Collection;
use SmoDav\MessageBroker\Supervisors\SupervisorOptions;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

class WorkerPool
{
    /** @var Collection<WorkerProcess> */
    protected Collection $processes;

    /** @var Collection<WorkerProcess> */
    protected Collection $terminating;

    /** @var bool */
    protected bool $working = true;

    /** @var Closure */
    protected Closure $output;

    /**
     * @param SupervisorOptions $options
     * @param string            $directory
     * @param Closure|null      $output
     */
    public function __construct(protected SupervisorOptions $options, protected string $directory, Closure|null $output = null)
    {
        $this->processes = collect();
        $this->terminating = collect();

        $this->output = $output ?: function () {
        };
    }

    /**
     * Get the worker count.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->processes->count();
    }

    /**
     * Evaluate the current state of the processes.
     *
     * @return void
     */
    public function monitor()
    {
        $this->processes->each(fn (WorkerProcess $process) => $process->monitor());
    }

    /**
     * Pause the workers.
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    public function pause(): void
    {
        $this->working = false;

        $this->processes->each(fn (WorkerProcess $process) => $process->pause());
    }

    /**
     * Continue the workers..
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    public function continue(): void
    {
        $this->working = true;

        $this->processes->each(fn (WorkerProcess $process) => $process->continue());
    }

    /**
     * Terminate all current workers and start fresh ones.
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    public function restart(): void
    {
        $count = count($this->processes);

        $this->scale(0);

        $this->scale($count);
    }

    /**
     * Set the workers to the desired count.
     *
     * @param int $target
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    public function scale(int $target): void
    {
        $target = max(0, $target);
        $current = $this->processes->count();

        if ($target > $current) {
            $this->scaleUp($target);

            return;
        }

        if ($target < $current) {
            $this->scaleDown($target);
        }
    }

    /**
     * Scale down the workers.
     *
     * @param int $target
     *
     * @throws ExceptionInterface
     *
     * @return void
     */
    public function scaleDown(int $target): void
    {
        $difference = $this->processes->count() - $target;

        $toRemove = $this->processes->splice(0, $difference);

        $toRemove->each(function (WorkerProcess $process) {
            $process->terminate();
            $this->terminating->push($process);
        });
    }

    /**
     * Scale up the workers.
     *
     * @param int $target
     *
     * @return void
     */
    protected function scaleUp(int $target): void
    {
        $difference = $target - $this->processes->count();

        for ($i = 0; $i < $difference; $i++) {
            $this->start();
        }
    }

    /**
     * Start a new worker process.
     *
     * @return $this
     */
    public function start(): self
    {
        $this->processes->push($this->createProcess());

        return $this;
    }

    /**
     * Create a new worker process.
     *
     * @return WorkerProcess
     */
    protected function createProcess(): WorkerProcess
    {
        $count = count($this->processes);

        $options = new WorkerOptions(
            stream: $this->options->stream,
            group: $this->options->name,
            name: "{$this->options->name}-{$count}",
            timeout: $this->options->timeout,
            pending: $count === 0,
            sleep: $this->options->sleepWorker,
            restart: $this->options->restartWorker,
        );

        $command = new Command($options);

        $process = new WorkerProcess(
            Process::fromShellCommandline((string) $command, $this->directory)
                ->setTimeout(null)
                ->disableOutput()
        );

        return $process->handleOutputUsing(fn ($type, $line) => call_user_func($this->output, $type, $line));
    }

    /**
     * Remove any non-running processes from the terminating process list.
     *
     * @return Collection<WorkerProcess>
     */
    public function pruneTerminatingProcesses(): Collection
    {
        $this->killHangingTerminatingProcesses();

        $this->terminating = $this->terminating->filter(fn (WorkerProcess $process) => $process->isRunning());

        return $this->terminating;
    }

    /**
     * Stop any terminating processes that have hanged.
     *
     * @return void
     */
    protected function killHangingTerminatingProcesses(): void
    {
        $this->terminating->each(function (WorkerProcess $process) {
            if ($process->hangedAfterTermination($this->options->timeout)) {
                $process->stop();
            }
        });
    }
}
