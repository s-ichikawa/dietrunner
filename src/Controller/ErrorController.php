<?php

namespace Sichikawa\Dietrunner\Controller;

use Sichikawa\Dietrunner\Controller;

class ErrorController extends Controller implements ErrorControllerInterface
{
    public function notFound()
    {
        $this->getResponse()->withStatus(404);
        return $this->render('error404');
    }

    public function methodNotAllowed()
    {
        $this->getResponse()->withStatus(403);
        return $this->render('error403');
    }

    public function internalError(\Exception $error)
    {
        $this->getResponse()->withStatus(500);
        return $this->render('error500', ['error' => $error]);
    }
}
