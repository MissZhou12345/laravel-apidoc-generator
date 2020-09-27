<?php
return [
    /*
    * If you would like the package to generate the same example values for parameters on each run,
    * set this to any number (eg. 1234)
    *
    */
    'faker_seed' => null,
    'title' => env('APP_NAME', 'apidoc-generator'),
    'description' => env('APIDOC_DESCRIPTION', 'description'),
    'version' => env('APIDOC_VERSION', '1.0.0'),
    'contact' => [
        'name' => env('APIDOC_CONTACT_NAME', 'Mz'),
        'email' => env('APIDOC_CONTACT_EMAIL', '517013774@qq.com')
    ],
    'servers' => [
        [
            'url' => env('APP_URL', 'http://localhost'),
            'description' => env('APP_ENV', 'local')
        ]
    ],
    /**
     * 根据项目不同，定义不同的通用字段
     */
    'columnComments' => [
        'totalPages' => '总共页数',
    ],
];
