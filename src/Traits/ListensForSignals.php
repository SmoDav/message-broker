<?php

namespace SmoDav\MessageBroker\Traits;

trait ListensForSignals
{
    /**
     * The received signals that are yet to be processed.
     *
     * @var array
     */
    protected $receivedSignals = [];

    /**
     * Handle the continue signal.
     *
     * @return void
     */
    abstract protected function continue(): void;

    /**
     * Handle the pause signal.
     *
     * @return void
     */
    abstract protected function pause(): void;

    /**
     * Handle the restart signal.
     *
     * @return void
     */
    abstract protected function restart(): void;

    /**
     * Handle the terminate signal.
     *
     * @param int $status
     *
     * @return void
     */
    abstract protected function terminate(int $status = 0): void;

    /**
     * Listen for incoming process signals.
     *
     * @return void
     */
    protected function listenForSignals()
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->receivedSignals['terminate'] = true;
        });

        pcntl_signal(SIGUSR1, function () {
            $this->receivedSignals['restart'] = true;
        });

        pcntl_signal(SIGUSR2, function () {
            $this->receivedSignals['pause'] = true;
        });

        pcntl_signal(SIGCONT, function () {
            $this->receivedSignals['continue'] = true;
        });
    }

    /**
     * Process the pending signals.
     *
     * @return void
     */
    protected function processPendingSignals()
    {
        while (!empty($this->receivedSignals)) {
            $signal = array_key_first($this->receivedSignals);

            $this->{$signal}();

            unset($this->receivedSignals[$signal]);
        }
    }
}
