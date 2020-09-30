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
                        'type' => 'object'
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

                        'responses' => $this->getResponses($route['response']),

                        'consumes' => [
                            "multipart/form-data"
                        ],
//                        'requestBody' => $this->getRequestBody($route['contentType'], $route['parameters']),
                        'parameters' => array_merge(
                            $this->getParameters($route['parameters'], 'formData'),
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
            "swagger"=>  "2.0",// TODO 暂时写死，版本更改很有可能也导致json格式改变

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

    private function getResponses($response)
    {
        $httpCode = [200];

        $responseData = json_decode($response);
        if (empty($responseData)) {
            return [];
        }

        $res = [
            'description' => 'description',
            'schema' => [
                '$schema' => "http://json-schema.org/draft-04/schema#",
                'properties' => [],
                'required' => [],
                'type' => 'object',
            ],
        ];
        $properties = [];
        foreach ($responseData as $key => $item) {
            $properties[$key] = $this->dealResponsesStruct($item);
        }

        $res['schema']['properties'] = (object)$properties;

        return [
            200 => $res
        ];
    }

    private function dealResponsesStruct($item)
    {

        if (in_array(gettype($item), ['object', 'array']) && isset($item->_____key)) {
            return [
                'description' => $item->description,
                "example" => $item->value,
                "default" => $item->value,
                'type' => $item->type,
            ];
        } else if (in_array(gettype($item), ['object', 'array'])) {
            $properties = [];
            foreach ($item as $k => $v) {
                if ($k == '_____description') {
                    continue;
                }
                $properties[$k] = $this->dealResponsesStruct($v);
            }
            return [
                'description' => $item->_____description ?? gettype($item) . " description",
                'properties' => (object)$properties,
                'type' => 'object',
            ];
        } else {
            return [
                'description' => $item->description ?? null,
                "example" => $item->value ?? null,
                "default" => $item->value ?? null,
                'type' => $item->type ?? null,
            ];

        }
    }


    /**
     *
     * getParameters
     *
     * @param $parameters
     * @param $in "body", "formData", "query", "header", "path" or "cookie".
     * @return array
     */
    private function getParameters($parameters, $in)
    {
        return collect($parameters)->map(function ($parameter, $key) use ($in) {
            $description = $parameter['description'];
            if (is_array($parameter['description'])) {
                $description = $parameter['description'] ? implode('|', $parameter['description']) : '';
            }
            return [
                'name' => $key,
                "in" => $in,// "body", "formData", "query", "header", "path" or "cookie".
                "description" => $description,
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
