<?php

namespace SmoDav\MessageBroker\Commands;

use Illuminate\Console\Command;
use SmoDav\MessageBroker\Traits\CommandSendsSignal;

class PauseProcessing extends Command
{
    use CommandSendsSignal;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message-broker:pause {group} {stream=microservices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pause the running message broker supervisor.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->sendSignal(SIGUSR2, 'Paused supervisor');

        return 0;
    }
}
