<?php
declare(strict_types=1);

use Webman\Config;

/*
 * 将插件自带的示例配置载入 webman 配置容器，
 * 使单元测试中可以读取 config('plugin.tinywan.storage.app.storage.*')
 */
Config::load(dirname(__DIR__) . '/src/config');
