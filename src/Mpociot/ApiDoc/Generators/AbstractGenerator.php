<?php

namespace Mpociot\ApiDoc\Generators;

use Faker\Factory;
use Mpociot\ApiDoc\Tools\DocumentationConfig;
use ReflectionClass;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Mpociot\ApiDoc\Parsers\RuleDescriptionParser as Description;

abstract class AbstractGenerator
{
    protected static $columns = [];
    protected $config;
    protected $swaggerCollection;

    public function __construct(DocumentationConfig $config, $swaggerCollection)
    {
        self::$columns = [];

        $this->setConfig($config);
        $this->setSwaggerCollection($swaggerCollection);
    }

    public function setSwaggerCollection($swaggerCollection)
    {
        $this->swaggerCollection = $swaggerCollection;
    }

    public function getSwaggerCollection()
    {
        return $this->swaggerCollection;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param $route
     *
     * @return mixed
     */
    abstract public function getUri($route);

    /**
     * @param $route
     *
     * @return mixed
     */
    abstract public function getMethods($route);

    /**
     * @param \Illuminate\Routing\Route $route
     * @param array $bindings
     * @param bool $withResponse
     *
     * @return array
     */
    abstract public function processRoute($route, $bindings = [], $headers = [], $withResponse = true);

    /**
     * Prepares / Disables route middlewares.
     *
     * @param bool $disable
     *
     * @return  void
     */
    abstract public function prepareMiddleware($disable = false);

    /**
     * Get the response from the docblock if available.
     *
     * @param array $tags
     *
     * @return mixed
     */
    protected function getDocblockResponse($tags)
    {
        $responseTags = collect($tags)->filter(function ($tag) {
            if (!($tag instanceof Tag)) {
                return false;
            }
            return strtolower($tag->getName()) == 'response';
        });
        if (empty($responseTags)) {
            return;
        }

        $responseTag = $responseTags->first();
        if (empty($responseTag)) {
            return;
        }

        return \response(\json_encode($responseTag->getContent()));
    }


    /**
     * @param array $routeData
     * @param array $routeAction
     * @param array $bindings
     *
     * @return mixed
     */
    protected function getParameters($routeData, $routeAction, $bindings)
    {
        $attributes = $this->getRouteAttributes($routeAction['uses'], $bindings);

        $validator = Validator::make([], $this->getRouteRules($routeAction['uses'], $bindings));

        $parameters = $this->getAttributeData($routeData['id'], $validator->getRules(), $attributes);

        $routeData['parameters'] = $parameters;

        return $routeData;
    }

    /**
     *
     * 过滤掉必填参数
     * @param $route
     * @return array
     */
    private function filterParameterRequied($route)
    {
        preg_match_all('/\{(.*?)\}/', $route->getDomain() . $route->uri, $matches);
        return collect($matches[1])->filter(function ($param) {
            return strpos($param, '?') === false;
        });
    }

    protected function getHeaderParameters($routeId, $route, $routeAction)
    {
        $validator = Validator::make([], $this->getRouteHeaderRules($routeAction['uses'], []));

        $rules = (array)$validator->getRules();

        return $this->getAttributeData($routeId, $rules);
    }

    protected function getQueryParameters($routeId, $route, $routeAction)
    {
        $validator = Validator::make([], $this->getRouteQueryRules($routeAction['uses'], []));

        $rules = (array)$validator->getRules();

        return $this->getAttributeData($routeId, $rules);
    }

    protected function getContentType($routeId, $route, $routeAction)
    {
       return $this->getContentTypeStr($routeAction['uses'], []);
    }
    protected function getRouteParameters($routeId, $route, $routeAction)
    {
        $wheres = collect($route->wheres);
        $parameterNames = $route->parameterNames();
        $parameterRequied = $this->filterParameterRequied($route);


        $parameterRules = [];
        foreach ($parameterNames as $parameterName) {
            $rules = [];
            $requied = $parameterRequied->filter(function ($param) use ($parameterName) {
                return $param == $parameterName;
            })->first();

            $pattern = $wheres->filter(function ($param, $key) use ($parameterName) {
                return $key == $parameterName;
            })->first();

            $rules[] = empty($requied) ? 'sometimes' : 'required';
            if (!empty($pattern)) {
                $rules[] = "regex:/^{$pattern}&/";
            }

            $parameterRules[$parameterName] = $rules;
        }

        $validator = Validator::make([], $this->getRouteRouteRules($routeAction['uses'], []));

        $rules = (array)$validator->getRules();
        foreach ($parameterRules as $key => &$parameterRule) {
            // 屏蔽掉 required|sometimes|regex
            foreach ($rules[$key] ?? [] as $rule) {
                if (strpos($rule, 'required') !== false
                    || strpos($rule, 'sometimes') !== false
                    || strpos($rule, 'regex') !== false) {
                    continue;
                }
                $parameterRule[] = $rule;
            }
        }

        return $this->getAttributeData($routeId, $parameterRules);
    }

    /**
     *
     * getAttributeData
     *
     * @param $routeId
     * @param $list
     * @return array
     */
    public function getAttributeData($routeId, $list, $attributes = [])
    {
        $parameters = [];
        foreach ($list as $attribute => $rules) {
            $attributeData = [
                'required' => false,
                'type' => null,
                'default' => '',
                'value' => '',
                'description' => [],
            ];

            $rule = collect($rules)->filter(function ($r) {
                return strpos($r, 'describe') !== false;
            });
            if (!empty($rule->first())) {
                $ruleAnalyzing = explode(':', $rule->first(), 2);
                $attributeData['description'][] = $ruleAnalyzing[1];
            } else {
                if (isset($attributes[$attribute]) && $attributes[$attribute]) {
                    $attributeData['description'][] = $attributes[$attribute];
                }
            }

            foreach ($rules as $ruleName => $rule) {
                $this->parseRule($rule, $attribute, $attributeData, $routeId);
            }
            $parameters[$attribute] = $attributeData;
        }
        return $parameters;
    }

    /**
     * @param  $route
     * @param  $bindings
     * @param  $headers
     *
     * @return \Illuminate\Http\Response
     */
    protected function getRouteResponse($route, $bindings, $headers = [])
    {
        $uri = $this->addRouteModelBindings($route, $bindings);

        $methods = $this->getMethods($route);

        // Split headers into key - value pairs
        $headers = collect($headers)->map(function ($value) {
            $split = explode(':', $value); // explode to get key + values
            $key = array_shift($split); // extract the key and keep the values in the array
            $value = implode(':', $split); // implode values into string again

            return [trim($key) => trim($value)];
        })->collapse()->toArray();

        //Changes url with parameters like /users/{user} to /users/1
        $uri = preg_replace('/{(.*?)}/', 1, $uri);

        return $this->callRoute(array_shift($methods), $uri, [], [], [], $headers);
    }

    /**
     * @param $route
     * @param array $bindings
     *
     * @return mixed
     */
    protected function addRouteModelBindings($route, $bindings)
    {
        $uri = $this->getUri($route);

        foreach ($bindings as $model => $id) {
            $uri = str_replace('{' . $model . '}', $id, $uri);
        }

        return $uri;
    }

    /**
     * @param \Illuminate\Routing\Route $route
     *
     * @return string
     */
    protected function getRouteDescription($route)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);
        $reflectionMethod = $reflection->getMethod($method);

        $comment = $reflectionMethod->getDocComment();
        $phpdoc = new DocBlock($comment);

        return [
            'short' => $phpdoc->getShortDescription(),
            'long' => $phpdoc->getLongDescription()->getContents(),
            'tags' => $phpdoc->getTags(),
        ];
    }

    /**
     *
     * getRouteMethod
     *
     * describe：
     *
     * @param $route
     * @return mixed
     * @throws \ReflectionException
     */
    protected function getRouteMethod($route)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);
        $reflectionMethod = $reflection->getMethod($method);

        return $reflectionMethod->name;
    }

    /**
     * @param string $route
     *
     * @return string
     */
    protected function getRouteGroup($route)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);
        $comment = $reflection->getDocComment();
        if ($comment) {
            $phpdoc = new DocBlock($comment);
            foreach ($phpdoc->getTags() as $tag) {
                if ($tag->getName() === 'resource') {
                    return $tag->getContent();
                }
            }
        }

        return 'general';
    }

    /**
     * @param  $route
     * @param array $bindings
     *
     * @return array
     */
    protected function getRouteRules($uses, $bindings)
    {
        $parameterReflection = $this->getFormRequestReflection($uses, $bindings);
        if (empty($parameterReflection)) {
            return [];
        }

        if (method_exists($parameterReflection, 'validator')) {
            return $parameterReflection->validator()->getRules();
        } else {
            return $parameterReflection->rules();
        }
    }

    /**
     *
     * getRouteAttributes
     *
     * @param $route
     * @param $bindings
     * @return array
     * @throws \ReflectionException
     */
    protected function getRouteAttributes($route, $bindings)
    {
        $parameterReflection = $this->getFormRequestReflection($route, $bindings);
        if (empty($parameterReflection)) {
            return [];
        }

        if (method_exists($parameterReflection, 'attributes')) {
            return $parameterReflection->attributes();
        } else {
            return [];
        }
    }

    /**
     *
     * 获取验证类的  routeRules() 方法 用于取描述和字段类型
     *
     * @param $uses
     * @param $bindings
     * @return array
     * @throws \ReflectionException
     */
    protected function getRouteRouteRules($uses, $bindings)
    {
        $parameterReflection = $this->getFormRequestReflection($uses, $bindings);
        if (empty($parameterReflection)) {
            return [];
        }

        if (method_exists($parameterReflection, 'routeRules')) {
            return $parameterReflection->routeRules();
        } else {
            return [];
        }
    }

    protected function getContentTypeStr($uses, $bindings)
    {
        $parameterReflection = $this->getFormRequestReflection($uses, $bindings);
        if (empty($parameterReflection)) {
            return 'application/json';
        }

        if (method_exists($parameterReflection, 'getSwaggerContentType')) {
            return $parameterReflection->getSwaggerContentType();
        } else {
            return 'application/json';
        }
    }

    protected function getRouteHeaderRules($uses, $bindings)
    {
        $parameterReflection = $this->getFormRequestReflection($uses, $bindings);
        if (empty($parameterReflection)) {
            return [];
        }

        if (method_exists($parameterReflection, 'headerRules')) {
            return $parameterReflection->headerRules();
        } else {
            return [];
        }
    }

    protected function getRouteQueryRules($uses, $bindings)
    {
        $parameterReflection = $this->getFormRequestReflection($uses, $bindings);
        if (empty($parameterReflection)) {
            return [];
        }

        if (method_exists($parameterReflection, 'queryRules')) {
            return $parameterReflection->queryRules();
        } else {
            return [];
        }
    }

    /**
     *
     * getFormRequestReflection
     *
     * @param $route
     * @param $bindings
     * @return null
     * @throws \ReflectionException
     */
    private function getFormRequestReflection($uses, $bindings)
    {
        list($class, $method) = explode('@', $uses);
        $reflection = new ReflectionClass($class);
        $reflectionMethod = $reflection->getMethod($method);

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $parameterType = $parameter->getClass();
            if (!is_null($parameterType) && class_exists($parameterType->name)) {
                $className = $parameterType->name;

                if (is_subclass_of($className, FormRequest::class)) {
                    $parameterReflection = new $className;
                    // Add route parameter bindings
                    $parameterReflection->query->add($bindings);
                    $parameterReflection->request->add($bindings);

                    return $parameterReflection;
                }
            }
        }

        return null;
    }


    /**
     * @param array $arr
     * @param string $first
     * @param string $last
     *
     * @return string
     */
    protected function fancyImplode($arr, $first, $last)
    {
        $arr = array_map(function ($value) {
            return '`' . $value . '`';
        }, $arr);
        array_push($arr, implode($last, array_splice($arr, -2)));

        return implode($first, $arr);
    }

    protected function splitValuePairs($parameters, $first = 'is ', $last = 'or ')
    {
        $attribute = '';
        collect($parameters)->map(function ($item, $key) use (&$attribute, $first, $last) {
            $attribute .= '`' . $item . '` ';
            if (($key + 1) % 2 === 0) {
                $attribute .= $last;
            } else {
                $attribute .= $first;
            }
        });
        $attribute = rtrim($attribute, $last);

        return $attribute;
    }

    /**
     * @param string $rule
     * @param string $ruleName
     * @param array $attributeData
     * @param int $seed
     *
     * @return void
     */
    protected function parseRule($rule, $ruleName, &$attributeData, $seed)
    {
        $faker = Factory::create();
        $faker->seed(crc32($seed));

        $parsedRule = $this->parseStringRule($rule);
        $parsedRule[0] = $this->normalizeRule($parsedRule[0]);
        list($rule, $parameters) = $parsedRule;

        switch ($rule) {
            case 'required':
                $attributeData['required'] = true;
                break;
            case 'accepted':
                $attributeData['required'] = true;
                $attributeData['type'] = 'boolean';
                $attributeData['value'] = true;
                break;
            case 'after':
                $attributeData['type'] = 'date';
                $attributeData['format'] = 'date-time';
                $attributeData['description'][] = Description::parse($rule)->with(date('Y-m-d H:i:s', strtotime($parameters[0])))->getDescription();
                $attributeData['value'] = date('Y-m-d H:i:s', strtotime('+1 day', strtotime($parameters[0])));
                break;
            case 'alpha':
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                $attributeData['value'] = $faker->word;
                break;
            case 'alpha_dash':
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                break;
            case 'alpha_num':
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                break;
            case 'in':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                $attributeData['value'] = $faker->randomElement($parameters);
                break;
            case 'not_in':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                $attributeData['value'] = $faker->word;
                break;
            case 'min':
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                if (Arr::get($attributeData, 'type') === 'numeric') {
                    $attributeData['value'] = $faker->numberBetween($parameters[0]);
                    $attributeData['format'] = 'double';
                } else if (Arr::get($attributeData, 'type') === 'integer') {
                    $attributeData['value'] = $faker->numberBetween($parameters[0]);
                    $attributeData['format'] = 'int64';
                }
                break;
            case 'max':
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                if (Arr::get($attributeData, 'type') === 'numeric') {
                    $attributeData['value'] = $faker->numberBetween(0, $parameters[0]);
                    $attributeData['format'] = 'double';
                } else if (Arr::get($attributeData, 'type') === 'integer') {
                    $attributeData['value'] = $faker->numberBetween(0, $parameters[0]);
                    $attributeData['format'] = 'int64';
                }
                break;
            case 'between':
                if (!isset($attributeData['type'])) {
                    $attributeData['type'] = 'numeric';
                    $attributeData['format'] = 'double';
                }
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                $attributeData['value'] = $faker->numberBetween($parameters[0], $parameters[1]);
                break;
            case 'before':
                $attributeData['type'] = 'date';
                $attributeData['format'] = 'date-time';
                $attributeData['description'][] = Description::parse($rule)->with(date('Y-m-d H:i:s', strtotime($parameters[0])))->getDescription();
                $attributeData['value'] = date('Y-m-d H:i:s', strtotime('-1 day', strtotime($parameters[0])));
                break;
            case 'date_format':
                $attributeData['type'] = 'date';
                $attributeData['format'] = 'date-time';
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                $attributeData['value'] = date($parameters[0]);
                break;
            case 'different':
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                break;
            case 'digits':
                $attributeData['type'] = 'numeric';
                $attributeData['format'] = 'double';
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                $attributeData['value'] = ($parameters[0] < 9) ? $faker->randomNumber($parameters[0], true) : substr(mt_rand(100000000, mt_getrandmax()), 0, $parameters[0]);
                break;
            case 'digits_between':
                $attributeData['type'] = 'numeric';
                $attributeData['format'] = 'double';
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                break;
            case 'file':
                $attributeData['type'] = 'file';
                $attributeData['format'] = 'binary';
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                break;
            case 'image':
                $attributeData['type'] = 'image';
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                break;
            case 'json':
                $attributeData['type'] = 'string';
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                $attributeData['value'] = json_encode(['foo', 'bar', 'baz']);
                break;
            case 'mimetypes':
            case 'mimes':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                break;
            case 'required_if':
                $attributeData['description'][] = Description::parse($rule)->with($this->splitValuePairs($parameters))->getDescription();
                break;
            case 'required_unless':
                $attributeData['description'][] = Description::parse($rule)->with($this->splitValuePairs($parameters))->getDescription();
                break;
            case 'required_with':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                break;
            case 'required_with_all':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' and '))->getDescription();
                break;
            case 'required_without':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                break;
            case 'required_without_all':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' and '))->getDescription();
                break;
            case 'same':
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                break;
            case 'size':
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                break;
            case 'timezone':
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                $attributeData['value'] = $faker->timezone;
                break;
            case 'exists':
                $fieldName = isset($parameters[1]) ? $parameters[1] : $ruleName;
                $attributeData['description'][] = Description::parse($rule)->with([Str::singular($parameters[0]), $fieldName])->getDescription();
                break;
            case 'active_url':
                $attributeData['type'] = 'url';
                $attributeData['value'] = $faker->url;
                break;
            case 'regex':
                $attributeData['type'] = 'string';
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                break;
            case 'boolean':
                $attributeData['value'] = true;
                $attributeData['type'] = $rule;
                break;
            case 'array':
                $attributeData['value'] = $faker->word;
                $attributeData['type'] = $rule;
                break;
            case 'date':
                $attributeData['value'] = $faker->date();
                $attributeData['type'] = $rule;
                break;
            case 'email':
                $attributeData['value'] = $faker->safeEmail;
                $attributeData['type'] = $rule;
                break;
            case 'string':
                $attributeData['value'] = $faker->word;
                $attributeData['type'] = $rule;
                break;
            case 'integer':
                $attributeData['value'] = $faker->randomNumber();
                $attributeData['type'] = $rule;
                $attributeData['format'] = 'int64';
                break;
            case 'numeric':
                $attributeData['value'] = $faker->randomNumber();
                $attributeData['type'] = $rule;
                $attributeData['format'] = 'double';
                break;
            case 'url':
                $attributeData['value'] = $faker->url;
                $attributeData['type'] = $rule;
                break;
            case 'ip':
                $attributeData['value'] = $faker->ipv4;
                $attributeData['type'] = $rule;
                break;
        }

        if ($attributeData['value'] === '') {
            $attributeData['value'] = $faker->word;
        }

        if (is_null($attributeData['type'])) {
            $attributeData['type'] = 'string';
        }
//        dd($attributeData);
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
    abstract public function callRoute($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null);

    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     *
     * @param array $headers
     *
     * @return array
     */
    protected function transformHeadersToServerVars(array $headers)
    {
        $server = [];
        $prefix = 'HTTP_';

        foreach ($headers as $name => $value) {
            $name = strtr(strtoupper($name), '-', '_');

            if (!Str::startsWith($name, $prefix) && $name !== 'CONTENT_TYPE') {
                $name = $prefix . $name;
            }

            $server[$name] = $value;
        }

        return $server;
    }

    /**
     * Parse a string based rule.
     *
     * @param string $rules
     *
     * @return array
     */
    protected function parseStringRule($rules)
    {
        $parameters = [];

        // The format for specifying validation rules and parameters follows an
        // easy {rule}:{parameters} formatting convention. For instance the
        // rule "Max:3" states that the value may only be three letters.
        if (strpos($rules, ':') !== false) {
            list($rules, $parameter) = explode(':', $rules, 2);

            $parameters = $this->parseParameters($rules, $parameter);
        }

        return [strtolower(trim($rules)), $parameters];
    }

    /**
     * Parse a parameter list.
     *
     * @param string $rule
     * @param string $parameter
     *
     * @return array
     */
    protected function parseParameters($rule, $parameter)
    {
        if (strtolower($rule) === 'regex') {
            return [$parameter];
        }

        return str_getcsv($parameter);
    }

    /**
     * Normalizes a rule so that we can accept short types.
     *
     * @param string $rule
     *
     * @return string
     */
    protected function normalizeRule($rule)
    {
        switch ($rule) {
            case 'int':
                return 'integer';
            case 'bool':
                return 'boolean';
            default:
                return $rule;
        }
    }
}
