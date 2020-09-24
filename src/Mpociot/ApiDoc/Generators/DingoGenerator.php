<?php

namespace Mpociot\ApiDoc\Generators;

use Exception;
use Mpociot\ApiDoc\Strategies\ContentTypeParameters\GetContentTypeParamTag;
use Mpociot\ApiDoc\Strategies\CookieParameters\GetFromCookieParamTag;
use Mpociot\ApiDoc\Strategies\HeaderParameters\GetFromHeaderParamTag;
use Mpociot\ApiDoc\Strategies\PathParameters\GetFromPathParamTag;
use Mpociot\ApiDoc\Strategies\QueryParameters\GetFromQueryParamTag;

class DingoGenerator extends AbstractGenerator
{
    /**
     * @param \Illuminate\Routing\Route $route
     * @param array $bindings
     * @param array $headers
     * @param bool $withResponse
     *
     * @return array
     */
    public function processRoute($route, $bindings = [], $headers = [], $withResponse = true)
    {
        $response = '';

        if ($withResponse) {
            try {
                $response = $this->getRouteResponse($route, $bindings, $headers);
            } catch (Exception $e) {
            }
        }

        $routeAction = $route->getAction();
        $routeGroup = $this->getRouteGroup($routeAction['uses']);
        $routeDescription = $this->getRouteDescription($routeAction['uses']);
        $routeMethod = $this->getRouteMethod($routeAction['uses']);

        $queryParamTag = new GetFromQueryParamTag($this->getConfig());
        $pathParamTag = new GetFromPathParamTag($this->getConfig());
        $headerParamTag = new GetFromHeaderParamTag($this->getConfig());
        $cookieParamTag = new GetFromCookieParamTag($this->getConfig());
        $contentTypeParamTag = new GetContentTypeParamTag($this->getConfig());

        return $this->getParameters([
            'id' => md5($route->uri() . ':' . implode($route->getMethods())),
            'resource' => $routeGroup,
            'title' => $routeDescription['short'],
            'description' => $routeDescription['long'],
            'methods' => $route->getMethods(),
            'uri' => $route->uri(),
            'parameters' => [],
            'routeMethod' => $routeMethod,
            'contentType' => $contentTypeParamTag->getContentTypeFromDocBlock($routeDescription['tags']),
            'pathParameters' => $pathParamTag->getPathParametersFromDocBlock($routeDescription['tags']),
            'queryParameters' => $queryParamTag->getQueryParametersFromDocBlock($routeDescription['tags']),
            'headerParameters' => $headerParamTag->getHeaderParametersFromDocBlock($routeDescription['tags']),
            'cookieParameters' => $cookieParamTag->getCookieParametersFromDocBlock($routeDescription['tags']),
            'response' => $response,
        ], $routeAction, $bindings);
    }

    /**
     * Prepares / Disables route middlewares.
     *
     * @param bool $disable
     *
     * @return  void
     */
    public function prepareMiddleware($disable = true)
    {
        // Not needed by Dingo
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function callRoute($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $dispatcher = app('Dingo\Api\Dispatcher')->raw();

        collect($server)->map(function ($key, $value) use ($dispatcher) {
            $dispatcher->header($value, $key);
        });

        return call_user_func_array([$dispatcher, strtolower($method)], [$uri]);
    }

    /**
     * {@inheritdoc}
     */
    public function getUri($route)
    {
        return $route->uri();
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods($route)
    {
        return $route->getMethods();
    }
}
