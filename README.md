# webman 简单易用多文件上传插件

[![Latest Stable Version](http://poser.pugx.org/tinywan/storage/v)](https://packagist.org/packages/tinywan/storage) [![Total Downloads](http://poser.pugx.org/tinywan/storage/downloads)](https://packagist.org/packages/tinywan/storage) 
[![Daily Downloads](http://poser.pugx.org/tinywan/storage/d/daily)](https://packagist.org/packages/tinywan/storage)
[![Latest Unstable Version](http://poser.pugx.org/tinywan/storage/v/unstable)](https://packagist.org/packages/tinywan/storage) 
[![License](http://poser.pugx.org/tinywan/storage/license)](https://packagist.org/packages/tinywan/storage) 
[![PHP Version Require](http://poser.pugx.org/tinywan/storage/require/php)](https://packagist.org/packages/tinywan/storage)
[![last-commit](https://img.shields.io/github/last-commit/tinywan/storage/main)]()
[![storage tag](https://img.shields.io/github/v/tag/tinywan/storage?color=ff69b4)]()

目前支持以下多文件上传

- `local：本地对象存储`
- `oss：阿里云对象存储`
- `cos：腾讯云对象存储`
- `qiniu：七牛云对象存储`

## 安装

```php
composer require tinywan/storage
```

## 基本用法

```php
use Tinywan\Storage\Storage;
use Tinywan\Storage\Exception\StorageException;

try {
    // 初始化
    Storage::config(); // 默认为本地存储：local
    // 上传
    $res = Storage::uploadFile();
    var_dump(json_encode($res));
}catch (StorageException $exception) {
    var_dump($exception->getMessage());
}
```

### 上传成功信息
```json
[
    {
        "key": "webman",
        "origin_name": "常用编程软件和工具.xlsx",
        "save_name": "03414c9bdaf7a38148742c87b96b8167.xlsx",
        "save_path": "runtime/storage/03414c9bdaf7a38148742c87b96b8167.xlsx",
        "save_path": "/var/www/webman-admin/public/storage/03414c9bdaf7a38148742c87b96b8167.xlsx",
        "url": "/storage/fd2d472da56c71a6da0a5251f5e1b586.png",
        "uniqid ": "03414c9bdaf7a38148742c87b96b8167",
        "size": 15050,
        "mime_type": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "extension": "xlsx"
    }
    ...
]
```
> 失败，抛出`StorageAdapterException`异常
### 成功响应字段

| 字段|描述|示例值|
|:---|:---|:---|
|key | 上传文件key | webman |
|origin_name |原始文件名 | 常用编程软件和工具.xlsx |
|save_name |保存文件名 | 03414c9bdaf7a38148742c87b96b8167.xlsx |
|save_path|文件保存路径（相对） | /var/www/webman-admin/runtime/storage/03414c9bdaf7a38148742c87b96b8167.xlsx|
|url |url访问路径 | /storage/03414c9bdaf7a38148742c87b96b8167.xlsx|
|unique_id|uniqid | 03414c9bdaf7a38148742c87b96b8167|
|size |文件大小 | 15050（字节）|
|mime_type |文件类型 | application/vnd.openxmlformats-officedocument.spreadsheetml.sheet|
|extension |文件扩展名 | xlsx|
## 上传规则

默认情况下是上传到本地服务器，会在`runtime/storage`目录下面生成以当前日期为子目录，以文件流的sha1编码为文件名的文件，例如上面生成的文件名可能是：
```
runtime/storage/fd2d472da56c71a6da0a5251f5e1b586.png
```
如果你希望上传的文件是可以直接访问或者下载的话，可以使用`public`存储方式。

你可以在`config/plugin/tinywan/storage/pp.php`配置文件中配置上传根目录，例如：

```php
'local' => [
    'adapter' => \Tinywan\Storage\Adapter\LocalAdapter::class,
    'root' => public_path() . '/storage',
],
```
> 浏览器访问：http://127.0.0.1:8787/storage/fd2d472da56c71a6da0a5251f5e1b586.png

## 上传验证

支持使用验证类对上传文件的验证，包括文件大小、文件类型和后缀

| 字段|描述|示例值|
|:---|:---|:---|
|single_limit | 单个文件的大小限制，默认200M | 1024 * 1024 * 200 |
|total_limit | 所有文件的大小限制，默认200M | 1024 * 1024 * 200 |
|nums | 文件数量限制，默认10 | 10 |
|include | 被允许的文件类型列表 | ['xlsx','pdf'] |
|exclude | 不被允许的文件类型列表 | ['png','jpg'] |

## 支持上传SDK

#### 阿里云对象存储

```php
composer require aliyuncs/oss-sdk-php
```
#### 腾讯云对象存储

```php
composer require qcloud/cos-sdk-v5
```

#### 七牛云云对象存储

```php
composer require qiniu/php-sdk
```

## Other

### phpstan

```php
vendor/bin/phpstan analyse src
```

### vendor/bin/php-cs-fixer fix src

```php
vendor/bin/php-cs-fixer fix src
```