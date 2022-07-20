<?php

namespace SmoDav\MessageBroker\Commands;

use Illuminate\Console\Command;
use SmoDav\MessageBroker\Jobs\ProcessIncomingMessages;
use SmoDav\MessageBroker\Workers\Worker;
use SmoDav\MessageBroker\Workers\WorkerOptions;

class ListenAsChild extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message-broker:worker {stream} {group} {name} {--pending=false} {--timeout=} {--sleep=} {--restart=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to the message broker as a worker.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('default_socket_timeout', -1);

        $stream = $this->argument('stream');
        $group = $this->argument('group');
        $name = $this->argument('name');

        $timeout = (int) ($this->option('timeout') ?? '60');
        $sleep = (float) ($this->option('sleep') ?? '1');
        $restart = (int) ($this->option('restart') ?? '60');
        $pending = $this->option('pending') == 'true';

        ProcessIncomingMessages::createGroup($stream, $group);

        $options = new WorkerOptions($stream, $group, $name, $timeout, $pending, $sleep, $restart);

        $worker = new Worker(
            $options,
            fn () => new ProcessIncomingMessages($options, $stream, $group, $name),
            fn ($message) => $this->output->writeln($message),
        );

        $worker->run();

        return 0;
    }
}
