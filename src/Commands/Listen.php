<?php

namespace SmoDav\MessageBroker\Commands;

use Illuminate\Console\Command;
use SmoDav\MessageBroker\Supervisors\Supervisor;
use SmoDav\MessageBroker\Supervisors\SupervisorOptions;
use Symfony\Component\Process\Exception\ExceptionInterface;

class Listen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message-broker:listen {group} {stream=microservices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to the message broker.';

    /**
     * Execute the console command.
     *
     * @throws ExceptionInterface
     *
     * @return int
     */
    public function handle()
    {
        $config = new SupervisorOptions($this->argument('stream'), $this->argument('group'), 2, 3);

        $supervisor = new Supervisor($config, fn ($type, $line) => $this->output->write($line), base_path());

        $this->info('Supervisor started successfully.');

        pcntl_async_signals(true);

        pcntl_signal(SIGINT, function () use ($supervisor) {
            $this->line('Shutting down...');

            $supervisor->terminate();
        });

        $supervisor->monitor();

        return 0;
    }
}
