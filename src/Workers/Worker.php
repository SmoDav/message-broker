<?php

namespace SmoDav\MessageBroker\Workers;

use Carbon\CarbonImmutable;
use Closure;
use SmoDav\MessageBroker\Contracts\Job;
use SmoDav\MessageBroker\Enums\ExitCode;
use Throwable;

class Worker
{
    /** @var bool */
    protected bool $shouldQuit = false;

    /** @var bool */
    protected bool $paused = false;

    /** @var CarbonImmutable */
    protected CarbonImmutable $startTime;

    public function __construct(protected WorkerOptions $options, protected Closure $getNextJobHandler, protected Closure $output)
    {
    }

    /**
     * Sendt the given output.
     *
     * @param string $message
     *
     * @return void
     */
    protected function sendOutput(string $message): void
    {
        call_user_func($this->output, $message);
    }

    /**
     * Run the worker.
     *
     * @return ExitCode
     */
    public function run(): ExitCode
    {
        $this->listenForSignals();
        $this->startTime = CarbonImmutable::now();

        while (true) {
            if (!$this->shouldRun()) {
                $exitCode = $this->pauseExecution();

                if (!is_null($exitCode)) {
                    return $exitCode;
                }
                continue;
            }

            /** @var Job $job */
            $job = ($this->getNextJobHandler)();

            $job->prepare();

            if ($job->ready()) {
                $this->setTimeoutHandler();

                $class = get_class($job);

                try {
                    $time = CarbonImmutable::now()->toDateTimeString();

                    $this->sendOutput("{$time} Processing {$class}");
                    $job->handle();

                    $time = CarbonImmutable::now()->toDateTimeString();
                    $this->sendOutput("{$time} Processed {$class}");
                } catch (Throwable $e) {
                    $time = CarbonImmutable::now()->toDateTimeString();
                    $this->sendOutput("{$time} Failed {$class}");
                }

                $this->resetTimeoutHandler();
            }

            $exitCode = $this->pauseExecution();

            if (!is_null($exitCode)) {
                return $exitCode;
            }
        }
    }

    /**
     * Enable async signals for the process.
     *
     * @return void
     */
    protected function listenForSignals(): void
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->shouldQuit = true;
        });

        pcntl_signal(SIGUSR2, function () {
            $this->paused = true;
        });

        pcntl_signal(SIGCONT, function () {
            $this->paused = false;
        });
    }

    /**
     * Pause the execution of the jobs and return an exit code if necessary.
     *
     * @return ExitCode|null
     */
    protected function pauseExecution(): ExitCode|null
    {
        $this->sleep($this->options->sleep);

        return $this->stopExitCode();
    }

    /**
     * Check if the worker should stop and return an exit code if true.
     *
     * @return ExitCode|null
     */
    protected function stopExitCode(): ExitCode|null
    {
        if ($this->shouldQuit || CarbonImmutable::now()->diffInSeconds($this->startTime) > $this->options->restart) {
            return ExitCode::SUCCESS;
        }

        return null;
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @param int|float $seconds
     *
     * @return void
     */
    public function sleep(int|float $seconds): void
    {
        if ($seconds < 1) {
            usleep($seconds * 1000000);
        } else {
            sleep($seconds);
        }
    }

    /**
     * Validate if the job should run.
     *
     * @return bool
     */
    protected function shouldRun(): bool
    {
        return !$this->paused;
    }

    /**
     * Set the timeout handler and kill the job when the alarm is raised..
     *
     * @return void
     */
    protected function setTimeoutHandler(): void
    {
        pcntl_signal(SIGALRM, function () {
            $this->kill(ExitCode::ERROR);
        });

        pcntl_alarm(max($this->options->timeout, 0));
    }

    /**
     * Kill the current worker process.
     *
     * @param ExitCode $exitCode
     *
     * @return never
     */
    public function kill(ExitCode $exitCode): never
    {
        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($exitCode->value);
    }

    /**
     * Reset the worker timeout handler.
     *
     * @return void
     */
    protected function resetTimeoutHandler(): void
    {
        pcntl_alarm(0);
    }
}
