<?php

namespace SmoDav\MessageBroker\Master;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SmoDav\MessageBroker\Traits\ListensForSignals;

class MasterSupervisor
{
    use ListensForSignals;

    /**
     * Indicates if the master supervisor process is working.
     *
     * @var bool
     */
    public $working = true;

    /**
     * The output handler.
     *
     * @var Closure|null
     */
    public $output;

    /**
     * The name of the master supervisor.
     *
     * @var string
     */
    public $name;

    /**
     * All of the supervisors managed.
     *
     * @var Collection
     */
    public $supervisors;

    /**
     * Create a new master supervisor instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->name = static::name();
        $this->supervisors = collect();

        $this->output = function () {
        };
    }

    /**
     * Get the name for this master supervisor.
     *
     * @return string
     */
    public static function name()
    {
        static $token;

        if (!$token) {
            $token = Str::random(4);
        }

        return static::basename() . '-' . $token;
    }
}
