<?php

namespace SmoDav\MessageBroker\Contracts;

interface Job
{
    /**
     * Prepare the job for execution.
     *
     * @return void
     */
    public function prepare(): void;

    /**
     * Check if the job is ready and can be handled.
     *
     * @return bool
     */
    public function ready(): bool;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void;
}
