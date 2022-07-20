<?php

namespace SmoDav\MessageBroker;

use Illuminate\Support\ServiceProvider;
use SmoDav\MessageBroker\Commands\ContinueProcessing;
use SmoDav\MessageBroker\Commands\Listen;
use SmoDav\MessageBroker\Commands\ListenAsChild;
use SmoDav\MessageBroker\Commands\PauseProcessing;
use SmoDav\MessageBroker\Commands\Terminate;

class MessageBrokerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ContinueProcessing::class,
                Listen::class,
                ListenAsChild::class,
                PauseProcessing::class,
                Terminate::class,
            ]);
        }
    }
}
