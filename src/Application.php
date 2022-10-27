<?php

namespace Sichikawa\Dietrunner;

use Dietcube\Exception\DCException;
use Sichikawa\Dietrunner\Exception\DRException;
use Sichikawa\Dietrunner\Components\ContainerAwareTrait;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Pimple\Container;

abstract class Application
{
    use ContainerAwareTrait;

    protected string $app_root;
    protected string $app_namespace;
    protected string $env;

    protected bool $debug = false;

    protected Config $config;

    protected array $dirs;

    protected $host;
    protected $protocol;
    protected $port;
    protected $path;
    protected $url;

    public function __construct(string $app_root, string $env)
    {
        $this->app_root = $app_root;
        $this->app_namespace = (new \ReflectionObject($this))->getNamespaceName();
        $this->env = $env;

        $this->dirs = $this->getDefaultDirs();
    }

    public function loadConfig()
    {
        $config = [];
        foreach ($this->getConfigFiles() as $config_file) {
            $load_config_file = $this->getConfigDir() . '/' . $config_file;
            if (!file_exists($load_config_file)) {
                continue;
            }

            $config = array_merge($config, require $load_config_file);
        }

        $this->config = new Config($config);
        $this->bootConfig();
    }

    public function init(Container $container)
    {
    }

    abstract public function config(Container $container);

    public function getAppNamespace(): string
    {
        return $this->app_namespace;
    }

    public function getRoute(): RouteInterface
    {
        $route_class = $this->getAppNamespace() . '\\Route';
        return new $route_class;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getConfigFiles()
    {
        return [
            'config.php',
            'config_' . $this->getEnv() . '.php',
        ];
    }

    public function getControllerByHandler($handler)
    {
        // @TODO check
        list($controller, $action_name) = explode('::', $handler, 2);
        if (!$controller || !$action_name) {
            throw new DRException('Error: handler error');
        }

        $controller_name = $this->getAppNamespace()
            . '\\Controller\\'
            . str_replace('/', '\\', $controller)
            . 'Controller';

        return [$controller_name, $action_name];
    }

    public function createController($controller_name)
    {
        $controller = new $controller_name($this->container);
        $controller->setVars('env', $this->getEnv());
        $controller->setVars('config', $this->container['app.config']->getData());

        return $controller;
    }

    protected function getDefaultDirs()
    {
        return [
            'controller' => $this->app_root . '/Controller',
            'config' => $this->app_root . '/config',
            'template' => $this->app_root . '/template',
            'resource' => $this->app_root . '/resource',
            'webroot' => dirname($this->app_root) . '/webroot',
            'tests' => dirname($this->app_root) . '/tests',
            'vendor' => dirname($this->app_root) . '/vendor',
            'tmp' => dirname($this->app_root) . '/tmp',
        ];
    }

    protected function bootConfig()
    {
        $this->debug = $this->config->get('debug', false);
    }

    /**
     * @return string
     */
    public function getEnv(): string
    {
        return $this->env;
    }

    public function getConfigDir()
    {
        return $this->dirs['config'];
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getTemplateDir()
    {
        return $this->dirs['template'];
    }

    public function getTemplateExt()
    {
        return '.html.twig';
    }

    public function isDebug()
    {
        return $this->debug;
    }

    public function createLogger(string $path, Level $level = Level::Warning): Logger
    {
        $logger = new Logger('app');
        $logger->pushProcessor(new PsrLogMessageProcessor);

        if (is_writable($path) || is_writable(dirname($path))) {
            $logger->pushHandler(new StreamHandler($path, $level));
        } else {
            if ($this->isDebug()) {
                throw new DRException("Log path '{$path}' is not writable. Make sure your logger.path of config.");
            }
            $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $level));
            $logger->warning("Log path '{$path}' is not writable. Make sure your logger.path of config.");
            $logger->warning("error_log() is used for application logger instead at this time.");
        }

        return $logger;
    }
}