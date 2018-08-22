<?php

namespace Mpociot\ApiDoc\Postman;

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

    public function getCollection()
    {
        $collection = [
            'variables' => [],
            'info' => [
                'name' => '',
                '_postman_id' => Uuid::uuid4()->toString(),
                'description' => '',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.0.0/collection.json',
            ],
            'item' => $this->routeGroups->map(function ($routes, $groupName) {
                return [
                    'name' => $groupName,
                    'description' => '',
                    'item' => $routes->map(function ($route) {
                        $result = [
                            'name' => $route['title'] != '' ? $route['title'] : url($route['uri']),
                            'request' => [
                                'url' => url($route['uri']),
                                'method' => $route['methods'][0],
                                'description' => $route['description'],
                                'response' => [],
                            ],
                        ];

                        if ($route['methods'][0] == 'GET') {
                            $host = config('app.url');
                            $host = str_replace('http://', '', $host);

                            $result['request']['body'] = (object)[];
                            $result['request']['header'] = [];
                            $result['request']['url'] = (object)[
                                'variable' => [],
                                'raw' => "",
                                'protocol' => "http",
                                'host' => explode('.', $host),
                                'path' => explode('/', $route['uri']),
                                'query' => collect($route['parameters'])->map(function ($parameter, $key) {
                                    return [
                                        'key' => $key,
                                        'value' => isset($parameter['value']) ? $parameter['value'] : '',
                                        "equals" => true,
                                        "description" => ""
                                    ];
                                })->values()->toArray()
                            ];


                        } else if ($route['methods'][0] == 'PUT') {
                            $result['request']['header'] = [
                                (object)[
                                    "key" => "Content-Type",
                                    "value" => "application/x-www-form-urlencoded",
                                    "description" => ""
                                ]
                            ];
                            $result['request']['body'] = [
                                'mode' => 'urlencoded',
                                'urlencoded' => collect($route['parameters'])->map(function ($parameter, $key) {
                                    return [
                                        'key' => $key,
                                        'value' => isset($parameter['value']) ? $parameter['value'] : '',
                                        'type' => 'text',
                                        'description' => '',
                                    ];
                                })->values()->toArray(),
                            ];
                        } else {
                            // post .....
                            $result['request']['body'] = [
                                'mode' => 'formdata',
                                'formdata' => collect($route['parameters'])->map(function ($parameter, $key) {
                                    return [
                                        'key' => $key,
                                        'value' => isset($parameter['value']) ? $parameter['value'] : '',
                                        'type' => 'text',
                                        'enabled' => true,
                                    ];
                                })->values()->toArray(),
                            ];
                        }
                        return $result;
                    })->toArray(),
                ];
            })->values()->toArray(),
        ];

        return json_encode($collection);
    }
}
