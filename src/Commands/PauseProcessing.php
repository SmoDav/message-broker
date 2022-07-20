<?php

namespace SmoDav\MessageBroker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use SmoDav\MessageBroker\Supervisors\Supervisor;

class PauseProcessing extends Command
{
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
        $key = Supervisor::getCacheKeyByName($this->argument('stream'), $this->argument('group'));

        $processId = Cache::get($key);

        if ($processId) {
            posix_kill($processId, SIGUSR2);
            $this->info("Paused supervisor with PID {$processId}");
        }

        return 0;
    }
}