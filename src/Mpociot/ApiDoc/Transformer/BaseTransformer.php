<?php

namespace Mpociot\ApiDoc\Transformer;

use Illuminate\Database\Eloquent\Model;
use League\Fractal\TransformerAbstract;

/**
 *
 *
 * Class    BaseTransformer
 *
 * describe： 提供一个基础的 Transformer
 *
 * Copyright  2020/9/27 11:51 上午 517013774@qq.com
 *
 * @resource  BaseTransformer
 * @license   MIT
 * @package   Mpociot\ApiDoc\Transformer
 * @author    Mz
 */
abstract class BaseTransformer extends TransformerAbstract
{
    /**
     * 该转换器中字段可能涉及到的表
     * 如果为空，则会取整个库
     * @var array
     */
    protected $tables = [];

    /**
     * 自定义字段注释
     * 当然你也可以通过 config('apidoc.columnComments') 去设置一些通用的字段注释
     * 优先级为：1：属性$columnComments；2：配置config('apidoc.columnComments')；3：$tables设置的表字段注释；4：整个库
     * @var array
     */
    protected $columnComments = [];

    /**
     *
     * getTables
     *
     * describe：
     *
     * @return array
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     *
     * getColumnComments
     *
     * describe：
     *
     * @return array
     */
    public function getColumnComments(): array
    {
        return array_merge(
            [
                'totalPages' => '总共页数',
                'totalCount' => '总共数据条数',
                'page' => '当前页码数',
                'limit' => '每页数据条数',
                'count' => '每页数据条数',
                'firstPage' => '第一页页码数',
                'lastPage' => '最后一页页码数',
                'hasPrePage' => '是否有上一页',
                'hasNextPage' => '是否有下一页',
                'prePage' => '上一页页码数',
                'nextPage' => '下一页页码数',
                'items' => '分页数据集',

                'result' => '返回结果',
                'message' => '返回的提示消息',
                'code' => 'code > 0 : 有错误出现',
            ],
            (array)config('apidoc.columnComments'),
            $this->columnComments,
        );
    }

    /**
     *
     * response
     *
     * describe：
     *
     * @return mixed
     */
    public function response()
    {
        return [];
    }

    /**
     *
     * transform
     *
     * @param Model $model
     * @return array
     */
    public function transform(\stdClass $model)
    {
        return [];
    }
}
