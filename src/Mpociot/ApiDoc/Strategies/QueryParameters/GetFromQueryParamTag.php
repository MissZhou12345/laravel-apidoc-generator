<?php

namespace Mpociot\ApiDoc\Strategies\QueryParameters;

use Mpociot\ApiDoc\Strategies\Strategy;
use Mpociot\Reflection\DocBlock\Tag;
use Illuminate\Support\Str;

class GetFromQueryParamTag extends Strategy
{

    public function getQueryParametersFromDocBlock($tags)
    {
        $parameters = collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'queryParam';
            })
            ->mapWithKeys(function (Tag $tag) {
                // Format:
                // @queryParam <name> <type> <"required" (optional)> <description>
                // Examples:
                // @queryParam text string required The text.
                // @queryParam user_id integer The ID of the user.
                preg_match('/(.+?)\s+(.+?)\s+(required\s+)?(.*)/', $tag->getContent(), $content);
                $content = preg_replace('/\s?No-example.?/', '', $content);
                if (empty($content)) {
                    // this means only name and type were supplied
                    list($name, $type) = preg_split('/\s+/', $tag->getContent());
                    $required = false;
                    $description = '';
                } else {
                    list($_, $name, $type, $required, $description) = $content;
                    $description = trim($description);
                    if ($description == 'required' && empty(trim($required))) {
                        $required = $description;
                        $description = '';
                    }
                    $required = trim($required) == 'required' ? true : false;
                }

                $type = $this->normalizeParameterType($type);
                list($description, $example) = $this->parseParamDescription($description, $type);
                $value = is_null($example) && ! $this->shouldExcludeExample($tag->getContent())
                    ? $this->generateDummyValue($type)
                    : $example;

                return [$name => compact('type', 'description', 'required', 'value')];
            })->toArray();

        return $parameters;
    }
}
