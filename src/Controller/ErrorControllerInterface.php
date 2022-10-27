<?php

namespace Sichikawa\Dietrunner\Controller;

interface ErrorControllerInterface
{
    public function notFound();

    public function methodNotAllowed();

    public function internalError(\Exception $error);
}