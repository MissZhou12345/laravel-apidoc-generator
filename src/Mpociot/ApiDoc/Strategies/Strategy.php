<?php

namespace Mpociot\ApiDoc\Strategies;

use Faker\Factory;
use Illuminate\Routing\Route;
use Mpociot\ApiDoc\Tools\DocumentationConfig;
use stdClass;
use ReflectionClass;
use ReflectionMethod;

abstract class Strategy
{
    /**
     * @var DocumentationConfig The apidoc config
     */
    protected $config;

    public function __construct(DocumentationConfig $config)
    {
        $this->config = $config;
    }

    protected function normalizeParameterType(string $type)
    {
        $typeMap = [
            'int' => 'integer',
            'bool' => 'boolean',
            'double' => 'float',
        ];

        return $type ? ($typeMap[$type] ?? $type) : 'string';
    }

    protected function parseParamDescription(string $description, string $type)
    {
        $example = null;
        if (preg_match('/(.*)\bExample:\s*(.+)\s*/', $description, $content)) {
            $description = trim($content[1]);

            // examples are parsed as strings by default, we need to cast them properly
            $example = $this->castToType($content[2], $type);
        }

        return [$description, $example];
    }
    protected function castToType(string $value, string $type)
    {
        $casts = [
            'integer' => 'intval',
            'int' => 'intval',
            'float' => 'floatval',
            'number' => 'floatval',
            'double' => 'floatval',
            'boolean' => 'boolval',
            'bool' => 'boolval',
        ];

        // First, we handle booleans. We can't use a regular cast,
        //because PHP considers string 'false' as true.
        if ($value == 'false' && ($type == 'boolean' || $type == 'bool')) {
            return false;
        }

        if (isset($casts[$type])) {
            return $casts[$type]($value);
        }

        return $value;
    }

    protected function shouldExcludeExample(string $description)
    {
        return strpos($description, ' No-example') !== false;
    }


    protected function generateDummyValue(string $type)
    {
        $faker = Factory::create();
        if ($this->config->get('faker_seed')) {
            $faker->seed($this->config->get('faker_seed'));
        }
        $fakeFactories = [
            'integer' => function () use ($faker) {
                return $faker->numberBetween(1, 20);
            },
            'number' => function () use ($faker) {
                return $faker->randomFloat();
            },
            'float' => function () use ($faker) {
                return $faker->randomFloat();
            },
            'boolean' => function () use ($faker) {
                return $faker->boolean();
            },
            'string' => function () use ($faker) {
                return $faker->word;
            },
            'array' => function () {
                return [];
            },
            'object' => function () {
                return new stdClass();
            },
        ];

        $fakeFactory = $fakeFactories[$type] ?? $fakeFactories['string'];

        return $fakeFactory();
    }

}
