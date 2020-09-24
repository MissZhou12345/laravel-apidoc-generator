<?php

namespace Mpociot\ApiDoc\Swagger;

use Mpociot\ApiDoc\Tools\DocumentationConfig;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Collection;

class CollectionWriter
{
    /**
     * @var Collection
     */
    private $routeGroups;

    /**
     * CollectionWriter constructor.
     *
     * @param Collection $routeGroups
     */
    public function __construct(Collection $routeGroups)
    {
        $this->routeGroups = $routeGroups;
    }

    private function getRequiredList($parameters)
    {
        return collect($parameters)->filter(function ($tag) {
            return $tag['required'] === true;
        })->map(function ($parameter, $key) {
            return $key;
        })->values()->toArray();
    }

    private function getPropertiesList($parameters)
    {
        $parameterList = [];
        foreach ($parameters as $name => $parameter) {
            $type = $parameter['type'];
            if (in_array($parameter['type'], ['date', 'image', 'file', 'url', 'email', 'ip'])) {
                // 转换为 swagger 支持的数据类型
                $type = 'string';
            } else if ($parameter['type'] == 'numeric') {
                // 转换为 swagger 支持的数据类型
                $type = 'number';
            }

            $param = [
                "description" => $parameter['description'] ? implode('|', $parameter['description']) : '',
                "type" => $type,
                "format" => $parameter['format'] ?? null,
            ];

            if (empty($param['format'])) {
                unset($param['format']);
            }

            $parameterList[$name] = (object)$param;

        }

        return (object)$parameterList;
    }

    private function getRequestBody($contentType, $parameters)
    {
        return (object)[
            'content' => (object)[
                $contentType => (object)[
                    'schema' => (object)[
                        'required' => $this->getRequiredList($parameters),
                        'properties' => $this->getPropertiesList($parameters),
                        'type' => 'object',
                    ]
                ]
            ]
        ];
    }

    public function getCollection(DocumentationConfig $config)
    {
        $paths = [];
        foreach ($this->routeGroups as $groupName => $routes) {

            foreach ($routes as $route) {
                $uri = $route['uri'];
                $routeList = [];

                foreach ($route['methods'] as $method) {
                    $routeList[strtolower($method)] = (object)[
                        'tags' => [$route['resource']],
                        'summary' => $route['title'],
                        'description' => $route['description'],
                        'operationId' => $route['routeMethod'],
                        'requestBody' => $this->getRequestBody($route['contentType'], $route['parameters']),
                        'parameters' => array_merge(
                            $this->getParameters($route['queryParameters'], 'query'),
                            $this->getParameters($route['pathParameters'], 'path'),
                            $this->getParameters($route['cookieParameters'], 'cookie'),
                            $this->getParameters($route['headerParameters'], 'header')
                        )
                    ];
                }

                $paths[$route['uri']] = (object)$routeList;
            }

        }

        $collection = [
            'openapi' => "3.0.0",// TODO 暂时写死，版本更改很有可能也导致json格式改变

            "info" => (object)[
                "title" => $config->get('title'),
                "description" => $config->get('description'),
                "contact" => (object)[
                    "name" => $config->get('contact.name'),
                    "email" => $config->get('contact.email')
                ],
                "version" => $config->get('version')
            ],

            "servers" => $config->get('servers'),

            'paths' => $paths,

            "security" => [
                [] // TODO 暂时未实现
            ]
        ];

        return json_encode($collection);
    }

    /**
     *
     * getParameters
     *
     * @param $parameters
     * @param $in "query", "header", "path" or "cookie".
     * @return array
     */
    private function getParameters($parameters, $in)
    {
        return collect($parameters)->map(function ($parameter, $key) use ($in) {
            return [
                'name' => $key,
                "in" => $in,// "query", "header", "path" or "cookie".
                "description" => $parameter['description'],
                "required" => $parameter['required'],
                "default" => $parameter['value'],
                "example" => $parameter['value'],
                "schema" => (object)[
                    "type" => $parameter['type']//"integer"
                ]
            ];
        })->values()->toArray();
    }
}
