<?php

namespace Sichikawa\Dietrunner;

use Pimple\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Controller
{
    const ACTION_NOT_FOUND = 'notFound';
    const ACTION_METHOD_NOT_ALLOWED = 'methodNotAllowed';
    const ACTION_INTERNAL_ERROR = 'internalError';

    protected Container $container;

    protected ?RequestInterface $request;

    protected array $view_vars;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param RequestInterface|null $request
     */
    public function setRequest(?RequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * @return $this
     */
    public function setVars($key, $value = null)
    {
        if (is_array($key)) {
            $this->view_vars = array_merge($this->view_vars, $key);
        } else {
            $this->view_vars[$key] = $value;
        }

        return $this;
    }

    protected function isPost()
    {
        return str_starts_with($this->request->getMethod(), 'post');
    }

    protected function get(string $name): mixed
    {
        return $this->container[$name];
    }

    protected function query($name, $default = null)
    {
        static $query;
        if (!$query) {
            parse_str($this->request->getUri()->getQuery(), $query);
        }

        return $query[$name] ?? $default;
    }

    protected function body($name, $default)
    {
        static $body;
        if (!$body) {
            parse_str($this->request->getBody()->getContents(), $body);
        }

        return $body[$name] ?? $default;
    }

//    protected function generateUrl($handler, array $data = [], array $query_params = [], $is_absolute = false)
//    {
//        return $this->container['router']->url($handler, $data, $query_params, $is_absolute);
//    }
//
    protected function findTemplate($name)
    {
        /**
         * @var Application $app
         */
        $app = $this->get('app');
        return $name . $app->getTemplateExt();
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    protected function render($name, array $vars = [])
    {
        $template = $this->findTemplate($name);

        /**
         * @var Environment $renderer
         */
        $renderer = $this->get('app.renderer');
        return $renderer->render($template, array_merge($this->view_vars, $vars));
    }
//
//    protected function redirect($uri, $code = 302)
//    {
//        $response = $this->getResponse();
//
//        $response->setStatusCode($code);
//        $response->setHeader('Location', $uri);
//
//        return null;
//    }
//
    /**
     * @return ResponseInterface
     */
    protected function getResponse()
    {
        return $this->get('response');
    }
//
//    /**
//     * Helper method to respond JSON.
//     *
//     * @param array $vars
//     * @param string|null $charset
//     * @return string JSON encoded string
//     */
//    protected function json($vars, $charset = 'utf-8')
//    {
//        $this->getResponse()->setHeader('Content-Type', 'application/json;charset=' . $charset);
//
//        return json_encode($vars);
//
//    }
}