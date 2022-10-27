<?php

namespace Sichikawa\Dietrunner\Twig;

use Sichikawa\Dietrunner\Components\ContainerAwareTrait;
use Sichikawa\Dietrunner\Router;
use Twig\Extension\AbstractExtension;

class DietrunnerExtension extends AbstractExtension
{
    use ContainerAwareTrait;

    public function url()
    {
        /**
         * @var Router $router
         */
        $router = $this->container->offsetGet('router');
        return $router->url();
    }
}