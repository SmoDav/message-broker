<?php

namespace SmoDav\MessageBroker\Commands;

use Illuminate\Console\Command;
use SmoDav\MessageBroker\Traits\CommandSendsSignal;

class Terminate extends Command
{
    use CommandSendsSignal;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message-broker:terminate {group} {stream=microservices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Terminate the running message broker supervisor.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->sendSignal(SIGTERM, 'Terminated supervisor');

        return 0;
    }
}
