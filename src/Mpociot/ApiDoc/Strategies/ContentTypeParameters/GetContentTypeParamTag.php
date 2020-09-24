<?php

namespace Mpociot\ApiDoc\Strategies\ContentTypeParameters;

use Mpociot\ApiDoc\Strategies\Strategy;
use Mpociot\Reflection\DocBlock\Tag;
use Illuminate\Support\Str;

class GetContentTypeParamTag extends Strategy
{

    public function getContentTypeFromDocBlock($tags)
    {
        $parameter = collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'contentType';
            })->first();

        $contentType = $parameter ? $parameter->getContent() : null;

        return $contentType ? trim($contentType) : "application/json";
    }

}
