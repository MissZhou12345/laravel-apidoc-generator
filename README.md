
## 注意注意注意！！！
https://github.com/mpociot/laravel-apidoc-generator <br/>
本项目是在以上项目基础之上修改

tips:继续采用request文件的验证规则作为bodyParameters
    mpociot/laravel-apidoc-generator已经放弃该做法
    

## 修改的清单


- 生成`collection_swagger.json` 需要加 `--swaggerCollection` 参数
```
public_path('docs/collection_swagger.json')
```
> 可以供yapi自动导入


- 规则校验新增 `describe` 规则

```
public function rules()
{
        return [
            'file' => 'required|file|describe:这里写描述.',
        ];
}
```
> `describe`只对生成文档有效，不作为laravel校验规则

- 如果字段中文名能作为描述：上述规则也可以使用laravel验证类的`attributes()`方法

```
<?php

namespace App\Http\Controllers\Admin\Requests;

use SalesTenant\Commons\Requests\Request;

/**
 *
 *
 * Class    LogIndexRequest
 *
 * describe：日志列表的验证码类
 *
 * ===========================================
 * Copyright  2020/9/27 3:20 下午 517013774@qq.com
 *
 * @resource  LogIndexRequest
 * @license   MIT
 * @package   App\Http\Controllers\Admin\Requests
 * @author    Mz
 */
class LogIndexRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user_id' => 'sometimes|string|max:32',
            'operator' => 'sometimes|string|max:128',
            'url' => 'sometimes|url|max:1024',
            'start_created_at' => 'sometimes|date',
            'end_created_at' => 'sometimes|date',
        ];
    }

    /**
     * 获取已定义验证规则的错误消息。
     *
     * @return array
     */
    public function messages()
    {
        return [
            'string' => ':attribute为字符串.',
            'max' => ':attribute最少:max个字符,',
            'url' => ':attribute为URL类型.',
            'date' => ':attribute为时间类型.',
        ];
    }

    /**
     *
     * attributes
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'user_id' => '用户ID',
            'operator' => '操作用户',
            'url' => '操作URL',
            'start_created_at' => '创建时间-开始时间',
            'end_created_at' => '创建时间-结束时间',
        ];
    }
}

```
> `describe`优先级大于`attributes()`方法



- `@contentType` 支持
```
  @contentType application/json
```

- `@urlParam` 支持(参照新版实现)
```
  @urlParam <name> <type> <"required" (optional)> <description>
  Examples:
  @urlParam text string required The text. Example: text
  @urlParam user_id integer The ID of the user. Example: 50

```

- `@queryParam` 支持(参照新版实现)
```
  @queryParam <name> <type> <"required" (optional)> <description>
  Examples:
  @queryParam text string required The text. Example: text
  @queryParam user_id integer The ID of the user. Example: 50

```

- `@cookieParam` 支持(参照新版实现)
```
  @cookieParam <name> <type> <"required" (optional)> <description>
  Examples:
  @cookieParam text string required The text. Example: text
  @cookieParam user_id integer The ID of the user. Example: 50

```

- `@headerParam` 支持(参照新版实现)
```
  @headerParam <name> <type> <"required" (optional)> <description>
  Examples:
  @headerParam text string required The text. Example: text
  @headerParam user_id integer The ID of the user. Example: 50

```

- `@responseTransformer` 写response 
```
/**
     *
     * 后台日志列表
     *
     * describe：后台日志管理列表
     *
     * @urlParam limit integer required 每页条数 Example: 30
     * @urlParam page integer required 当前分页 Example: 1
     * @urlParam sort string required 排序字段 Example: created_at
     * @urlParam order string required 排序方式 Example: DESC
     *
     * @responseTransformer App\Transformers\Admin\Log\IndexTransformer
     *
     * @param LogIndexRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function index(LogIndexRequest $request)
    {
        $this->getRouteParam($request);
        $data = app(AdminOperate::class)->search($request->all(),
            [$this->routeParam['sort'] => $this->routeParam['order']],
            $this->routeParam['page'],
            $this->routeParam['limit']);
        return $this->paginate($data);
    }
```

```
<?php

namespace App\Transformers\Admin\Log;

use Mpociot\ApiDoc\Transformer\BaseTransformer;

/**
 *
 *
 * Class    IndexTransformer
 *
 * describe：
 *
 * ===========================================
 * Copyright  2020/9/27 3:21 下午 517013774@qq.com
 *
 * @resource  IndexTransformer
 * @license   MIT
 * @package   App\Transformers\Admin\Log
 * @author    Mz
 */
class IndexTransformer extends BaseTransformer
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
     * 优先级为：1：配置config('apidoc.columnComments')；2：属性$columnComments；3：$tables设置的表字段注释；4：整个库
     * @var array
     */
    protected $columnComments = [];

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
        $res = <<<RES
{"totalPages":147,"totalCount":1462,"page":1,"limit":10,"count":10,"firstPage":true,"lastPage":false,"hasPrePage":false,"hasNextPage":true,"prePage":0,"nextPage":2,"items":[{"id":77392,"user_id":"0","operator":"\u7ba1\u7406\u5458","url":"\/api\/sales\/tenant\/shop\/config","method":"POST","request_data":"{\"shop_tenant_id\":\"98\",\"shop_config\":{\"hash_key\":\"kjN3S3r7yahJZ6wna7DRXZj48RTBXjDF2\"}}","md5":"31c51ede3ddb7fcc924075207e99d7f4","created_at":"2020-09-10 16:27:29","updated_at":"2020-09-10 16:27:29","deleted_at":null},{"id":77391,"user_id":"0","operator":"\u7ba1\u7406\u5458","url":"\/api\/sales\/tenant\/shop","method":"POST","request_data":"{\"source\":\"LWJ_SHOP\",\"name\":\"ddd\",\"tenant_code\":\"TEST08\"}","md5":"f9ff4d0215029eb40eae00224b3e8d09","created_at":"2020-09-10 16:27:21","updated_at":"2020-09-10 16:27:21","deleted_at":null},{"id":77390,"user_id":"0","operator":"\u7ba1\u7406\u5458","url":"\/api\/permission\/bindMenus","method":"PUT","request_data":"{\"id\":1,\"menuIds\":[]}","md5":"5c383d10b3f73a2da30603f584b72497","created_at":"2020-09-08 14:49:14","updated_at":"2020-09-08 14:49:14","deleted_at":null},{"id":77389,"user_id":"0","operator":"\u7ba1\u7406\u5458","url":"\/api\/sales\/user","method":"POST","request_data":"{\"mobile\":\"15982476445\"}","md5":"36e3471db1127c6d8c2b4b06a3b9a9f6","created_at":"2020-09-08 14:49:08","updated_at":"2020-09-08 14:49:08","deleted_at":null},{"id":77388,"user_id":"0","operator":"\u7ba1\u7406\u5458","url":"\/api\/sales\/user\/superAdmin","method":"POST","request_data":"{\"id\":\"e6b1934bddf84733bf1bb417b976105c\",\"is_admin\":0}","md5":"f0297ef6039e9a22aab17a86c9a4d1fa","created_at":"2020-09-08 14:49:05","updated_at":"2020-09-08 14:49:05","deleted_at":null},{"id":77387,"user_id":"0","operator":"\u7ba1\u7406\u5458","url":"\/api\/sales\/config","method":"PUT","request_data":"{\"sales_domain\":\"https:\/\/admin-sales-staging.liweijia.com:28043\",\"sales_oauth_client_id\":\"7aa8ca910e4e4726bb2afbeda283bdd1\",\"sales_oauth_client_secret\":\"ML8PQYRO\",\"sales_oauth_scope\":\"trust\",\"sales_sync\":\"1\",\"recipients\":\"zhoufengmin@liweijia.com|jiangzhiheng@liweijia.com|xuyesi@liweijia.com|liuminzhe@liweijia.com\",\"auto_retry_num\":\"5\"}","md5":"cd218015991a981232778df7d0c73688","created_at":"2020-09-08 14:48:46","updated_at":"2020-09-08 14:48:46","deleted_at":null},{"id":77386,"user_id":"0","operator":"\u7ba1\u7406\u5458","url":"\/api\/sales\/tenant\/import\/order","method":"POST","request_data":"{\"limit\":10,\"page\":1,\"tenant_code\":\"DSFPTCLOUD\",\"tenant_id\":123,\"sales_user_id\":\"ganwang0001\",\"mobile\":\"15777779003\",\"store_code\":\"AA0BBB76\",\"file\":\"\/upload\/templates\/1a5bed584172272a6438cecb49d4f20e.xlsx\",\"pwd\":\"UDlaV2FFUTlvcllWS0ZTWGs1TzQ5dz09\"}","md5":"cdd32781477204750eced9f3d43cfa93","created_at":"2020-09-08 14:48:30","updated_at":"2020-09-08 14:48:30","deleted_at":null},{"id":77385,"user_id":"0","operator":"\u7ba1\u7406\u5458","url":"\/api\/sales\/tenant\/import\/order","method":"POST","request_data":"{\"limit\":10,\"page\":1,\"tenant_code\":\"DSFPTCLOUD\",\"tenant_id\":123,\"sales_user_id\":\"ganwang0001\",\"mobile\":\"15777779003\",\"store_code\":\"AA0BBB76\",\"file\":\"\/upload\/templates\/1a5bed584172272a6438cecb49d4f20e.xlsx\",\"pwd\":\"UDlaV2FFUTlvcllWS0ZTWGs1TzQ5dz09\"}","md5":"cdd32781477204750eced9f3d43cfa93","created_at":"2020-09-08 14:47:26","updated_at":"2020-09-08 14:47:26","deleted_at":null},{"id":77384,"user_id":"0","operator":"\u7ba1\u7406\u5458","url":"\/api\/sales\/attachment\/excel","method":"POST","request_data":"{\"rename\":\"1\",\"type\":\"local\",\"folder\":\"templates\",\"file\":{}}","md5":"c6f4002c95f062961198cab600c3e861","created_at":"2020-09-08 14:47:24","updated_at":"2020-09-08 14:47:24","deleted_at":null},{"id":77383,"user_id":"0","operator":"\u7ba1\u7406\u5458","url":"\/api\/sales\/tenant\/import\/order","method":"POST","request_data":"{\"limit\":10,\"page\":1,\"tenant_code\":\"TEST06_710\",\"tenant_id\":184,\"sales_user_id\":\"ganwang0001\",\"mobile\":\"13800008989\",\"store_code\":\"HTQXL01\",\"file\":\"\/upload\/templates\/1a5bed584172272a6438cecb49d4f20e.xlsx\",\"pwd\":\"UDlaV2FFUTlvcllWS0ZTWGs1TzQ5dz09\"}","md5":"6dfde587d7b12ee5db01885a691f3a08","created_at":"2020-09-08 14:46:58","updated_at":"2020-09-08 14:46:58","deleted_at":null}]}
RES;
        return json_decode($res);
    }

}

```

## 建议

- 为自己添加一些 Live Templates
```
命令  注释

@help   查看帮助   
@dt data types（不清楚有哪些数据类型，参考）
@ct contentType的示例 
@hp headerParam的示例    
@cp cookieParam的示例    
@qp queryParam的示例  
@up urlParam的示例  

参考：
https://blog.csdn.net/tu1091848672/article/details/78670602
```
- 修改 `PHP Class Doc Comment`
```
/**
 *
 *
 * Class    ${NAME}
 * 
 * describe：
 *
 * ===========================================
 * Copyright  ${DATE} ${TIME} 517013774@qq.com
 *
 * @resource  ${NAME}
 * @license   MIT
 * @package   ${NAMESPACE}
 * @author    Mz
 */
 
 `@resource` 加上可以为接口分组
 
```



## Laravel API Documentation Generator

Automatically generate your API documentation from your existing Laravel routes. Take a look at the [example documentation](http://marcelpociot.de/whiteboard/).

`php artisan api:gen --routePrefix="settings/api/*"`

![image](http://img.shields.io/packagist/v/mpociot/laravel-apidoc-generator.svg?style=flat)
![image](http://img.shields.io/packagist/l/mpociot/laravel-apidoc-generator.svg?style=flat)
[![codecov.io](https://codecov.io/github/mpociot/laravel-apidoc-generator/coverage.svg?branch=master)](https://codecov.io/github/mpociot/laravel-apidoc-generator?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mpociot/laravel-apidoc-generator/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mpociot/laravel-apidoc-generator/?branch=master)
[![Build Status](https://travis-ci.org/mpociot/laravel-apidoc-generator.svg?branch=master)](https://travis-ci.org/mpociot/laravel-apidoc-generator)
[![StyleCI](https://styleci.io/repos/57999295/shield?style=flat)](https://styleci.io/repos/57999295)
[![Dependency Status](https://www.versioneye.com/php/mpociot:laravel-apidoc-generator/dev-master/badge?style=flat)](https://www.versioneye.com/php/mpociot:laravel-apidoc-generator/dev-master)


## Installation

你可以通过vcs的方式安装: 在`composer.json`中添加 `repositories` 属性

```
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/MissZhou12345/laravel-apidoc-generator.git"
  }
]
```
然后添加：`require-dev`
```
"require-dev": {
    "mpociot/laravel-apidoc-generator": "dev-master"
}
```
你就可以拉取本项目了





Require this package with composer using the following command:

```sh
$ composer require mpociot/laravel-apidoc-generator
```
Using Laravel < 5.5? Go to your `config/app.php` and add the service provider:

```php
Mpociot\ApiDoc\ApiDocGeneratorServiceProvider::class,
```

> Using Laravel < 5.4? Use version 1.0! For Laravel 5.4 and up, use 2.0 instead.

## Usage

To generate your API documentation, use the `api:generate` artisan command.

```sh
$ php artisan api:generate --routePrefix="api/v1/*"
```

This command will scan your applications routes for the URIs matching `api/v1/*` and will parse these controller methods and form requests. For example:

```php
// API Group Routes
Route::group(array('prefix' => 'api/v1', 'middleware' => []), function () {
    // Custom route added to standard Resource
    Route::get('example/foo', 'ExampleController@foo');
    // Standard Resource route
    Route::resource('example', 'ExampleController');
});
```

### Available command options:

Option | Description
--------- | -------
`output` | The output path used for the generated documentation. Default: `public/docs`
`routePrefix` | The route prefix to use for generation - `*` can be used as a wildcard
`routes` | The route names to use for generation - Required if no routePrefix is provided
`middleware` | The middlewares to use for generation
`noResponseCalls` | Disable API response calls
`noPostmanCollection` | Disable Postman collection creation
`useMiddlewares` | Use all configured route middlewares (Needed for Laravel 5.3 `SubstituteBindings` middleware)
`actAsUserId` | The user ID to use for authenticated API response calls
`router` | The router to use, when processing the route files (can be Laravel or Dingo - defaults to Laravel)
`bindings` | List of route bindings that should be replaced when trying to retrieve route results. Syntax format: `binding_one,id|binding_two,id`
`force` | Force the re-generation of existing/modified API routes
`header` | Custom HTTP headers to add to the example requests. Separate the header name and value with ":". For example: `--header="Authorization: CustomToken"`

## Publish rule descriptions for customisation or translation.

 By default, this package returns the descriptions in english. You can publish the packages language files, to customise and translate the documentation output.

 ```sh
 $ php artisan vendor:publish
 ```

 After the files are published you can customise or translate the descriptions in the language you want by renaming the `en` folder and editing the files in `public/vendor/apidoc/resources/lang`.


### How does it work?

This package uses these resources to generate the API documentation:

#### Controller doc block

This package uses the HTTP controller doc blocks to create a table of contents and show descriptions for your API methods.

Using `@resource` in a doc block prior to each controller is useful as it creates a Group within the API documentation for all methods defined in that controller (rather than listing every method in a single list for all your controllers), but using `@resource` is not required. The short description after the `@resource` should be unique to allow anchor tags to navigate to this section. A longer description can be included below.

Above each method within the controller you wish to include in your API documentation you should have a doc block. This should include a unique short description as the first entry. An optional second entry can be added with further information. Both descriptions will appear in the API documentation in a different format as shown below.

```php
/**
 * @resource Example
 *
 * Longer description
 */
class ExampleController extends Controller {

    /**
     * This is the short description [and should be unique as anchor tags link to this in navigation menu]
     *
     * This can be an optional longer description of your API call, used within the documentation.
     *
     */
     public function foo(){

     }
```

**Result:** 

![Doc block result](http://headsquaredsoftware.co.uk/images/api_generator_docblock.png)

#### Form request validation rules

To display a list of valid parameters, your API methods accepts, this package uses Laravels [Form Requests Validation](https://laravel.com/docs/5.2/validation#form-request-validation).


```php
public function rules()
{
    return [
        'title' => 'required|max:255',
        'body' => 'required',
        'type' => 'in:foo,bar',
        'thumbnail' => 'required_if:type,foo|image',
    ];
}
```

**Result:** ![Form Request](http://marcelpociot.de/documentarian/form_request.png)

#### Controller method doc block
It is possible to override the results for the response. This will also show the responses for other request methods then GET.

#### @transformer
With the transformer you can define the transformer that is used for the result of the method. It will try the next parts to get a result if it can find the transformer. The first successfull will be used.

1. Check if there is a transformermodel tag to define the model
2. Get a model from the modelfactory
2. If the parameter is a Eloquent model it will load the first from the database.
3. A new instance from the class

```php
/**
 * @transformer \Mpociot\ApiDoc\Tests\Fixtures\TestTransformer
 */
public function transformerTag()
{
    return '';
}
```

#### @transformercollection
This is the same idea as the @tranformer tag with one different, instead of the return of an item, it will generate the return of a set with two items

```php
/**
 * @transformercollection \Mpociot\ApiDoc\Tests\Fixtures\TestTransformer
 */
public function transformerCollectionTag()
{
    return '';
}
```

#### @transformermodel
The @transformermodel tag is needed for PHP 5.* to get the model. For PHP 7 is it optional to specify the model that is used for the transformer.

#### @response
If you expliciet want to specify the result of a function you can set it in the docblock

```php
/**
 * @response {
 *  data: [],
 *}
 */
public function responseTag()
{
    return '';
}
```

#### API responses

If your API route accepts a `GET` method, this package tries to call the API route with all middleware disabled to fetch an example API response. 

If your API needs an authenticated user, you can use the `actAsUserId` option to specify a user ID that will be used for making these API calls:

```sh
$ php artisan api:generate --routePrefix="api/*" --actAsUserId=1
```

If you don't want to automatically perform API response calls, use the `noResponseCalls` option.

```sh
$ php artisan api:generate --routePrefix="api/*" --noResponseCalls
```

> Note: The example API responses work best with seeded data.

#### Postman collections

The generator automatically creates a Postman collection file, which you can import to use within your [Postman App](https://www.getpostman.com/apps) for even simpler API testing and usage.

If you don't want to create a Postman collection, use the `--noPostmanCollection` option, when generating the API documentation.

As of Laravel 5.3, the default base URL added to the Postman collection will be that found in your Laravel `config/app.php` file. This will likely be `http://localhost`. If you wish to change this setting you can directly update the url or link this config value to your environment file to make it more flexible (as shown below):

```php
'url' => env('APP_URL', 'http://yourappdefault.app'),
```

If you are referring to the environment setting as shown above, then you should ensure that you have updated your `.env` file to set the APP_URL value as appropriate. Otherwise the default value (`http://yourappdefault.app`) will be used in your Postman collection. Example environment value:

```
APP_URL=http://yourapp.app
```

## Modify the generated documentation

If you want to modify the content of your generated documentation, go ahead and edit the generated `index.md` file.
The default location of this file is: `public/docs/source/index.md`.
 
After editing the markdown file, use the `api:update` command to rebuild your documentation as a static HTML file.

```sh
$ php artisan api:update
```

As an optional parameter, you can use `--location` to tell the update command where your documentation can be found.

## Skip single routes

If you want to skip a single route from a list of routes that match a given prefix, you can use the `@hideFromAPIDocumentation` tag on the Controller method you do not want to document.

## Further modification

This package uses [Documentarian](https://github.com/mpociot/documentarian) to generate the API documentation. If you want to modify the CSS files of your documentation, or simply want to learn more about what is possible, take a look at the [Documentarian guide](http://marcelpociot.de/documentarian/installation).

### License

The Laravel API Documentation Generator is free software licensed under the MIT license.
