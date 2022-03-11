# webman storage 存储插件

[![Latest Stable Version](http://poser.pugx.org/tinywan/storage/v)](https://packagist.org/packages/tinywan/storage)
[![Total Downloads](http://poser.pugx.org/tinywan/storage/downloads)](https://packagist.org/packages/tinywan/storage)
[![License](http://poser.pugx.org/tinywan/storage/license)](https://packagist.org/packages/tinywan/storage)
[![PHP Version Require](http://poser.pugx.org/tinywan/storage/require/php)](https://packagist.org/packages/tinywan/storage)
[![storage](https://img.shields.io/github/last-commit/tinywan/storage/main)]()
[![storage](https://img.shields.io/github/v/tag/tinywan/storage?color=ff69b4)]()

## 安装

```php
composer require tinywan/storage
```

## 基本用法

```php
use Tinywan\Storage;

// 初始化
Storage::config();

// 上传
$res = Storage::upload([]);
var_dump(json_encode($res));
```

### 上传成功信息
```json
[
    {
        "key": "webman",
        "origin_name": "常用编程软件和工具.xlsx",
        "save_name": "03414c9bdaf7a38148742c87b96b8167.xlsx",
        "save_path": "/upload/03414c9bdaf7a38148742c87b96b8167.xlsx",
        "uniqid ": "03414c9bdaf7a38148742c87b96b8167",
        "size": 15050,
        "mime_type": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "extension": "xlsx"
    }
    ...
]
```
> 失败，抛出异常
### 成功响应字段

| 字段|类型|描述|示例值|
|:---|:---|:---|:---|
|key | string | 上传文件key | webman |
|origin_name | string |原始文件名 | 常用编程软件和工具.xlsx |
|save_name| string |保存文件名 | 03414c9bdaf7a38148742c87b96b8167.xlsx |
|save_path| string |文件保存路径（相对） | /upload/03414c9bdaf7a38148742c87b96b8167.xlsx|
|unique_id| string |uniqid | 03414c9bdaf7a38148742c87b96b8167|
|size| int |文件大小 | 15050（字节）|
|mime_type| string |文件类型 | application/vnd.openxmlformats-officedocument.spreadsheetml.sheet|
|extension| string |文件扩展名 | xlsx|

## Other

### phpstan

```php
vendor/bin/phpstan analyse src
```

### vendor/bin/php-cs-fixer fix src

```php
vendor/bin/php-cs-fixer fix src
```