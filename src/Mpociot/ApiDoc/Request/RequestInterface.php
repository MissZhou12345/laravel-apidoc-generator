<?php

namespace Mpociot\ApiDoc\Request;

interface RequestInterface
{

    /**
     * 路由验证规则
     * tips: 生成文档时required|sometimes|regex等规则会被屏蔽掉，原因是：需要以路由参数为准
     * @return array
     */
    public function routeRules(): array;

    /**
     * header验证规则
     * @return array
     */
    public function headerRules(): array;

    /**
     *
     * getSwaggerContentType
     * 默认值：application/json
     *
     * @return string
     */
    public function getSwaggerContentType(): string;

}
