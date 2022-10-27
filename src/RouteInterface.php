<?php
namespace Sichikawa\Dietrunner;

use Pimple\Container;

interface RouteInterface
{
    public function definition(Container $container): array;
}
