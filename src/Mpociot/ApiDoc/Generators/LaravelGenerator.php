<?php

namespace Mpociot\ApiDoc\Generators;

use Illuminate\Pagination\LengthAwarePaginator;
use Mpociot\ApiDoc\Strategies\ContentTypeParameters\GetContentTypeParamTag;
use Mpociot\ApiDoc\Strategies\CookieParameters\GetFromCookieParamTag;
use Mpociot\ApiDoc\Strategies\HeaderParameters\GetFromHeaderParamTag;
use Mpociot\ApiDoc\Strategies\PathParameters\GetFromPathParamTag;
use Mpociot\ApiDoc\Strategies\QueryParameters\GetFromQueryParamTag;
use Mpociot\ApiDoc\Tools\Utils;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use League\Fractal\Manager;
use Illuminate\Routing\Route;
use League\Fractal\Resource\Item;
use Illuminate\Support\Facades\App;
use Mpociot\Reflection\DocBlock\Tag;
use Illuminate\Support\Facades\Request;
use League\Fractal\Resource\Collection;
use Illuminate\Foundation\Http\FormRequest;

class LaravelGenerator extends AbstractGenerator
{
    /**
     * @param Route $route
     *
     * @return mixed
     */
    public function getUri($route)
    {
        $uri = $route->uri();
        if (version_compare(app()->version(), '5.4', '<')) {
            $uri = $route->getUri();
        }
        return $uri;
    }

    /**
     * @param Route $route
     *
     * @return mixed
     */
    public function getMethods($route)
    {
        if (version_compare(app()->version(), '5.4', '<')) {
            return $route->getMethods();
        }

        return $route->methods();
    }

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
        $content = '';

        $routeAction = $route->getAction();
        $routeGroup = $this->getRouteGroup($routeAction['uses']);
        $routeDescription = $this->getRouteDescription($routeAction['uses']);
        $routeMethod = $this->getRouteMethod($routeAction['uses']);
        $showresponse = null;


        $cookieParamTag = new GetFromCookieParamTag($this->getConfig());
        $contentTypeParamTag = new GetContentTypeParamTag($this->getConfig());

        if ($withResponse) {
            $response = null;
            $docblockResponse = $this->getDocblockResponse($routeDescription['tags']);

            if ($docblockResponse) {
                // we have a response from the docblock ( @response )
                $response = $docblockResponse;
                $showresponse = true;
            }
            if (!$response) {
                // 为了不占用原先的关键字
                $transformerResponse = $this->getSwaggerTransformerResponse($routeDescription['tags']);
                if ($transformerResponse) {
                    // we have a transformer response from the docblock ( @transformer || @transformercollection )
                    $response = $transformerResponse;
                    $showresponse = true;
                }
            }
            if (!$response) {
                $transformerResponse = $this->getTransformerResponse($routeDescription['tags']);
                if ($transformerResponse) {
                    // we have a transformer response from the docblock ( @transformer || @transformercollection )
                    $response = $transformerResponse;
                    $showresponse = true;
                }
            }
            if (!$response) {
                $response = $this->getRouteResponse($route, $bindings, $headers);
            }
            if ($response->headers->get('Content-Type') === 'application/json') {
                $content = json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT);
            } else {
                $content = $response->getContent();
            }
        }

        $routeId = md5($this->getUri($route) . ':' . implode($this->getMethods($route)));
        return $this->getParameters([
            'id' => $routeId,
            'resource' => $routeGroup,
            'title' => $routeDescription['short'],
            'description' => $routeDescription['long'],
            'methods' => $this->getMethods($route),
            'uri' => str_replace('?', '', $this->getUri($route)),
            'parameters' => [],
            'routeMethod' => $routeMethod,
            'contentType' => $this->getContentType($routeId,$route,$routeAction),
            'pathParameters' => $this->getRouteParameters($routeId,$route,$routeAction),
            'headerParameters' => $this->getHeaderParameters($routeId,$route,$routeAction),
            'queryParameters' => [],
            'cookieParameters' => [],
            'response' => $content,
            'showresponse' => $showresponse,
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
        App::instance('middleware.disable', true);
    }

    /**
     * Call the given URI and return the Response.
     *
     * @param string $method
     * @param string $uri
     * @param array $parameters
     * @param array $cookies
     * @param array $files
     * @param array $server
     * @param string $content
     *
     * @return \Illuminate\Http\Response
     */
    public function callRoute($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $server = collect([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ])->merge($server)->toArray();

        $request = Request::create(
            $uri, $method, $parameters,
            $cookies, $files, $this->transformHeadersToServerVars($server), $content
        );

        $kernel = App::make('Illuminate\Contracts\Http\Kernel');
        $response = $kernel->handle($request);

        $kernel->terminate($request, $response);

        if (file_exists($file = App::bootstrapPath() . '/app.php')) {
            $app = require $file;
            $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        }

        return $response;
    }

    /**
     * Get a response from the transformer tags.
     *
     * @param array $tags
     *
     * @return mixed
     */
    protected function getTransformerResponse($tags)
    {
        try {
            $transFormerTags = collect($tags)->filter(function ($tag) {
                if (!($tag instanceof Tag)) {
                    return false;
                }

                return \in_array(\strtolower($tag->getName()), ['transformer', 'transformercollection']);
            });
            if (empty($transFormerTags)) {
                // we didn't have any of the tags so goodbye
                return false;
            }


            $modelTag = collect($tags)->filter(function ($tag) {
                if (!($tag instanceof Tag)) {
                    return false;
                }

                return \in_array(\strtolower($tag->getName()), ['transformermodel']);
            })->first();

            $tag = $transFormerTags->first();
            if (empty($tag)) {
                return;
            }

            $transformer = $tag->getContent();
            if (!\class_exists($transformer)) {
                // if we can't find the transformer we can't generate a response
                return;
            }
            $demoData = [];

            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('transform');
            $parameter = collect($method->getParameters())->first();
            $type = null;
            if ($modelTag) {
                $type = $modelTag->getContent();
            }

            if (version_compare(PHP_VERSION, '7.0.0') >= 0 && \is_null($type)) {
                // we can only get the type with reflection for PHP 7
                if ($parameter->hasType() &&
                    !$parameter->getType()->isBuiltin() &&
                    \class_exists($parameter->getType()->getName())) {
                    //we have a type
                    $type = (string)$parameter->getType()->getName();
                }
            }
            if ($type) {
                // we have a class so we try to create an instance
                $demoData = new $type;
                try {
                    // try a factory
                    $demoData = \factory($type)->make();
                } catch (\Exception $e) {
                    if ($demoData instanceof \Illuminate\Database\Eloquent\Model) {
                        // we can't use a factory but can try to get one from the database
                        try {
                            // check if we can find one
                            $newDemoData = $type::first();
                            if ($newDemoData) {
                                $demoData = $newDemoData;
                            }
                        } catch (\Exception $e) {
                            // do nothing
                        }
                    }
                }
            }

            $fractal = new Manager();
            $transformerModel = new $transformer;
            $resource = [];
            if ($tag->getName() == 'transformer') {
                // just one
                $resource = new Item($demoData, $transformerModel);
            }
            if ($tag->getName() == 'transformercollection') {
                // a collection
                $resource = new Collection([$demoData, $demoData], $transformerModel);
            }

            return \response($fractal->createData($resource)->toJson());
        } catch (\Exception $e) {
            // it isn't possible to parse the transformer
            return;
        }
    }

    protected function getSwaggerTransformerResponse($tags)
    {
        if (!$this->getSwaggerCollection()) {
            return;
        }

        try {
            $transFormerTags = collect($tags)->filter(function ($tag) {
                if (!($tag instanceof Tag)) {
                    return false;
                }
                return \in_array($tag->getName(), ['responseTransformer']);
            });
            if (empty($transFormerTags)) {
                // we didn't have any of the tags so goodbye
                return false;
            }

            $tag = $transFormerTags->first();
            if (empty($tag)) {
                return;
            }

            $transformer = $tag->getContent();
            if (!\class_exists($transformer)) {
                // if we can't find the transformer we can't generate a response
                return;
            }

            $transformerModel = new $transformer;
            $datas = $transformerModel->response();
            $tables = $transformerModel->getTables();
            $columnComments = $transformerModel->getColumnComments();

            $returnData = [];
            if (empty($tables)) {
                $tables = \DB::getDoctrineSchemaManager()->listTableNames();
            }
            $this->addComment($datas, $tables, $columnComments, $returnData);

            return \response(json_encode($returnData));
        } catch (\Exception $e) {
            // it isn't possible to parse the transformer
            return;
        }
    }


    private function getColumns($tables)
    {
        foreach ($tables as $table) {
            if (isset(self::$columns[$table]) && self::$columns[$table]) {
                continue;
            }
            self::$columns[$table] = \DB::getDoctrineSchemaManager()->listTableDetails($table);
        }
        return self::$columns;
    }

    protected function getComment(array $tables, array $columnComments, string $field)
    {
        $columns = $this->getColumns($tables);
        $comment = null;
        foreach ($columns as $column) {
            if (isset($columnComments[$field]) && $columnComments[$field]) {
                $comment = $columnComments[$field];
                break;
            }
            if (!$column->hasColumn($field)) {
                continue;
            }
            $comment = $column->getColumn($field)->getComment();
        }
        return $comment;
    }

    private function addComment($datas, $tables, $columnComments, &$returnData)
    {
        foreach ($datas as $key => $data) {
            if (in_array(gettype($data), ['object', 'array'])) {
                $this->addComment($data, $tables, $columnComments, $returnData[$key]);
                $returnData[$key]['_____description'] = $this->getComment($tables, $columnComments, $key);
            } else {
                $returnData[$key] = [
                    '_____key' => Uuid::uuid4()->toString(),
                    'value' => $data,
                    'type' => gettype($data),
                    'description' => $this->getComment($tables, $columnComments, $key),
                ];
            }
        }
    }


    /**
     * @param string $route
     * @param array $bindings
     *
     * @return array
     */
    protected function getRouteRules($route, $bindings)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);
        $reflectionMethod = $reflection->getMethod($method);

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $parameterType = $parameter->getClass();
            if (!is_null($parameterType) && class_exists($parameterType->name)) {
                $className = $parameterType->name;

                if (is_subclass_of($className, FormRequest::class)) {
                    $parameterReflection = new $className;
                    $parameterReflection->setContainer(app());
                    // Add route parameter bindings
                    $parameterReflection->query->add($bindings);
                    $parameterReflection->request->add($bindings);

                    if (method_exists($parameterReflection, 'validator')) {
                        return app()->call([$parameterReflection, 'validator'])
                            ->getRules();
                    } else {
                        return app()->call([$parameterReflection, 'rules']);
                    }
                }
            }
        }

        return [];
    }
}
