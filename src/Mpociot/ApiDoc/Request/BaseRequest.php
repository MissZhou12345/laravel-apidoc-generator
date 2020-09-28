<?php

namespace Mpociot\ApiDoc\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 *
 *
 * Class    BaseRequest
 *
 * describe：
 *
 * ===========================================
 * Copyright  2020/9/28 2:25 下午 517013774@qq.com
 *
 * @resource  BaseRequest
 * @license   MIT
 * @package   Mpociot\ApiDoc\Request
 * @author    Mz
 */
abstract class BaseRequest extends FormRequest implements RequestInterface
{

    /**
     * 路由验证规则
     * tips: 生成文档时required|sometimes|regex等规则会被屏蔽掉，原因是：需要以路由参数为准
     * @return array
     */
    public function routeRules(): array
    {
        return [];
    }

    /**
     * header验证规则
     * @return array
     */
    public function headerRules(): array
    {
        return [
            'Cookie' => 'required|string|describe:登录时需要的cookie.',
        ];
    }

    /**
     *
     * getSwaggerContentType
     *
     * @return string
     */
    public function getSwaggerContentType(): string
    {
        return 'application/json';
    }
    
}
