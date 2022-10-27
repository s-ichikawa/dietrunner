<?php

namespace Sichikawa\Dietrunner\Events;

use Sichikawa\Dietrunner\Application;
use Symfony\Contracts\EventDispatcher\Event;

class DietrunnerEventAbstract extends Event
{
    protected Application $app;

    /**
     * @return Application
     */
    public function getApplication(): Application
    {
        return $this->app;
    }

}