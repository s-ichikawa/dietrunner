<?php

namespace Sichikawa\Dietrunner;

use Sichikawa\Dietrunner\Events\BootEvent;
use Sichikawa\Dietrunner\Events\DietrunnerEvents;
use Sichikawa\Dietrunner\Events\ExecuteActionEvent;
use Sichikawa\Dietrunner\Events\RoutingEvent;
use Sichikawa\Dietrunner\Exception\DRException;
use Sichikawa\Dietrunner\Exception\HttpMethodNotAllowedException;
use Sichikawa\Dietrunner\Exception\HttpNotFoundException;
use Sichikawa\Dietrunner\Twig\DietrunnerExtension;
use Monolog\Level;
use Nyholm\Psr7\Response;
use Pimple\Container;
use FastRoute\Dispatcher as RouteDispatcher;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

class Dispatcher
{
    protected Application $app;

    protected Container $container;

    protected EventDispatcher $event_dispatcher;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function boot(): void
    {
        $this->app->loadConfig();

        $container = $this->container = new Container();

        $this->container->offsetSet('event_dispatcher', $this->event_dispatcher = new EventDispatcher());

        $this->container->offsetSet('app', $this->app);
        $this->app->setContainer($container);

        $config = $this->app->getConfig();
        $this->container->offsetSet('app.config', $config);

        $this->container['logger'] = $logger = $this->app->createLogger(
            $config->get('logger.path'),
            $config->get('logger.level', Level::Warning)
        );

        $logger->debug('Application booted. env={env}', ['env' => $this->app->getEnv()]);
        $logger->debug('Config file loaded. config_files={files}', ['files' => implode(',', $this->app->getConfigFiles())]);

        /*
         * TODO リクエストごとの初期化は別でやったほうがいいかも？
         * $this->app->initHttpRequest($this->container);
         */
        $this->app->init($this->container);

        if (!isset($this->container['router'])) {
            $router = new Router($container);
            $router->addRoute($this->app->getRoute());
            $this->container->offsetSet('router', $router);
        }

        if (!isset($this->container['app.renderer'])) {
            $this->container['app.renderer'] = function () {
                return $this->createRenderer();
            };
        }

        $this->app->config($this->container);

        $this->event_dispatcher->dispatch(new BootEvent($this->app), DietrunnerEvents::BOOT);
    }

    protected function createRenderer(): Environment
    {
        $config = $this->container['app.config'];
        $loader = new FilesystemLoader($this->app->getTemplateDir());
        $twig = new Environment($loader, [
            'debug' => $config->get('debug', false),
            'cache' => $config->get('twig.cache', false),
            'charset' => $config->get('twig.charset', 'utf-8'),
        ]);

        $loader->addPath(__DIR__ . '/template/error');

        $twig->addExtension((new DietrunnerExtension())->setContainer($this->container));

        if ($this->app->isDebug()) {
            // add built-in debug template path
            $twig->addExtension(new DebugExtension());
            $loader->addPath(__DIR__ . '/template/debug', 'debug');
        }

//        $twig->addGlobal('query', $this->container['global.get']->getData());
//        $twig->addGlobal('body', $this->container['global.post']->getData());

        return $twig;
    }

    public static function getEnv(): string
    {
        return getenv('DIET_ENV') ?? 'production';
    }

    public static function invoke(Application $app)
    {
        $dispatcher = new static($app);
        $dispatcher->boot();
    }

    public static function invokePsr7(Application $app, RequestInterface $request): ResponseInterface
    {
        $dispatcher = new static($app);
        $dispatcher->boot();

        try {
            return $dispatcher->handleRequest($request);
//        return new Response(200, [], 'Hello RoadRunner!');
        } catch (\Exception $exception) {
//            return $dispatcher->handlerError($exception);
            return new Response(500, [], 'Something wrong! ' . $exception->getMessage());
        }
    }

    public function handleRequest(RequestInterface $request): ResponseInterface
    {
        $container = $this->container;

        $this->event_dispatcher->addListener(DietrunnerEvents::ROUTING, function (RoutingEvent $event) use ($request) {
            list($handler, $vars) = $this->dispatchRouter($request->getMethod(), $request->getUri()->getPath());

            $event->setRouteInfo($handler, $vars);
        });

        $event = new RoutingEvent($this->app, $container['router']);
        $this->event_dispatcher->dispatch($event, DietrunnerEvents::ROUTING);

        list($handler, $vars) = $event->getRouteInfo();

        $action_result = $this->executeAction($handler, $vars);

        return new Response(200, [], $action_result);
    }

    protected function dispatchRouter(string $method, string $path)
    {
        $router = $this->container['router'];
        $logger = $this->container['logger'];

        $logger->debug('Router dispatch.', ['method' => $method, 'path' => $path]);

        $router->init();
        $route_info = $router->dispatch($method, $path);

        $handler = null;
        $vars = [];

        switch ($route_info[0]) {
            case RouteDispatcher::NOT_FOUND:
                $logger->debug('Routing failed. Not Found.');
                throw new HttpNotFoundException('404 Not Found');
                break;
            case RouteDispatcher::METHOD_NOT_ALLOWED:
                $logger->debug('Routing failed. Method Not Allowd.');
                throw new HttpMethodNotAllowedException('405 Method Not Allowed');
                break;
            case RouteDispatcher::FOUND:
                $handler = $route_info[1];
                $vars = $route_info[2];
                $logger->debug('Route found.', ['handler' => $handler]);
                break;
        }

        return [$handler, $vars];
    }

    public function executeAction($handler, $vars = [], $fire_events = true)
    {
        $logger = $this->container['logger'];
        $executable = null;
        if (is_callable($handler)) {
            $executable = $handler;
        } else {
            list($controller_name, $action_name) = $this->app->getControllerByHandler($handler);
            if (!class_exists($controller_name)) {
                throw new DRException("Controller {$controller_name} is not exists.");
            }
            $controller = $this->app->createController($controller_name);
            $executable = [$controller, $action_name];
        }

        if ($fire_events) {
            $event = new ExecuteActionEvent($this->app, $executable, $vars);
            $this->event_dispatcher->dispatch($event, DietrunnerEvents::EXECUTE_ACTION);

            $executable = $event->getExecutable();
            $vars = $event->getVars();
        }

        if ($executable instanceof \Closure) {
            $controller_name = 'function()';
            $action_name = '-';
        } else {
            $controller_name = get_class($executable[0]);
            $action_name = $executable[1];

            if (!is_callable($executable)) {
                // anon function is always callable so when the handler is anon function it never come here.
                $logger->error('Action not dispatchable.', ['controller' => $controller_name, 'action_name' => $action_name]);
                throw new DRException("'{$controller_name}::{$action_name}' is not a valid action.");
            }
        }

        $logger->debug('Execute action.', ['controller' => $controller_name, 'action' => $action_name, 'vars' => $vars]);
        return call_user_func_array($executable, $vars);
    }
}