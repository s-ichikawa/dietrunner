<?php

namespace Sichikawa\Dietrunner;

use FastRoute\DataGenerator\GroupCountBased as GroupCountBasedDataGenerator;
use FastRoute\Dispatcher\GroupCountBased as GroupCountBasedDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as StdRouteParser;
use Pimple\Container;

class Router
{
    protected GroupCountBasedDispatcher $dispatcher;

    protected Container $container;

    /**
     * @var RouteInterface[]
     */
    private array $routes;

    /**
     * @var string[]
     */
    private $named_routes;
    private string $dispatched_http_method;
    private string $dispatched_url;
    /**
     * @var array|mixed[]
     */
    private array $route_info;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function addRoute(RouteInterface $route)
    {
        $this->routes[] = $route;
        return $this;
    }

    public function init()
    {
        $collector = new RouteCollector(
            new StdRouteParser(),
            new GroupCountBasedDataGenerator()
        );

        foreach ($this->routes as $route) {
            foreach ($route->definition($this->container) as list($method, $route_name, $handler_name)) {
                $collector->addRoute($method, $route_name, $handler_name);
            }
        }

        $this->dispatcher = new GroupCountBasedDispatcher($collector->getData());
    }

    public function dispatch($http_method, $url)
    {
        if ($this->dispatcher === null) {
            throw new \RuntimeException('Route dispatcher is not initialized');
        }

        $this->dispatched_http_method = $http_method;
        $this->dispatched_url = $url;
        $this->route_info = $this->dispatcher->dispatch($http_method, $url);

        return $this->route_info;
    }

    /**
     * @param string $handler
     * @param array $data
     * @param array $query_params
     * @param bool $is_absolute
     * @return string
     */
    public function url(string $handler, array $data = [], array $query_params = [], bool $is_absolute = false): string
    {
        if ($this->named_routes === null) {
            $this->buildNameIndex();
        }

        if (!isset($this->named_routes[$handler])) {
            throw new \RuntimeException('Named route does not exist for name: ' . $handler);
        }

        $route = $this->named_routes[$handler];
        $url = preg_replace_callback('/{([^}]+)}/', function ($match) use ($data) {
            $segment_name = explode(':', $match[1])[0];
            if (!isset($data[$segment_name])) {
                throw new \InvalidArgumentException('Missing data for URL segment: ' . $segment_name);
            }
            return $data[$segment_name];
        }, $route);

        if ($query_params) {
            $url .= '?' . http_build_query($query_params);
        }

        if ($is_absolute) {
            /**
             * @var Application $app
             */
            $app = $this->container['app'];
            $url = $app->getUrl() . $url;
        }

        return $url;
    }

    protected function buildNameIndex()
    {
        $this->named_routes = [];

        foreach ($this->routes as $route) {
            foreach ($route->definition($this->container) as list($method, $route_name, $handler_name)) {
                if ($handler_name) {
                    $this->named_routes[$handler_name] = $route_name;
                }
            }
        }
    }


    /**
     * @return string
     */
    public function getDispatchedHttpMethod(): string
    {
        return $this->dispatched_http_method;
    }

    /**
     * @return string
     */
    public function getDispatchedUrl(): string
    {
        return $this->dispatched_url;
    }

    /**
     * @return array
     */
    public function getRouteInfo(): array
    {
        return $this->route_info;
    }
}