<?php

namespace Sichikawa\Dietrunner\Events;

use Sichikawa\Dietrunner\Application;

class BootEvent extends DietrunnerEventAbstract
{
    public function __construct(Application $app)
    {
        $this->app = $app;
    }
}