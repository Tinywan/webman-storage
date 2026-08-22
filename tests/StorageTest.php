<?php
declare(strict_types=1);

use Tinywan\Storage\Adapter\CosAdapter;
use Tinywan\Storage\Adapter\LocalAdapter;
use Tinywan\Storage\Adapter\OssAdapter;
use Tinywan\Storage\Adapter\QiniuAdapter;
use Tinywan\Storage\Adapter\S3Adapter;
use Tinywan\Storage\Storage;

test('存储模式常量与允许列表一致', function () {
    expect(Storage::$allowStorage)->toBe([
        Storage::MODE_LOCAL,
        Storage::MODE_OSS,
        Storage::MODE_COS,
        Storage::MODE_QINIU,
        Storage::MODE_S3,
    ]);
});

test('默认磁盘返回本地适配器', function () {
    expect(Storage::disk(null, false))->toBeInstanceOf(LocalAdapter::class);
});

test('按名称返回对应云存储适配器', function (string $disk, string $adapterClass) {
    expect(Storage::disk($disk, false))->toBeInstanceOf($adapterClass);
})->with([
    [Storage::MODE_LOCAL, LocalAdapter::class],
    [Storage::MODE_OSS,   OssAdapter::class],
    [Storage::MODE_COS,   CosAdapter::class],
    [Storage::MODE_QINIU, QiniuAdapter::class],
    [Storage::MODE_S3,    S3Adapter::class],
]);

test('getDefaultStorage 返回默认存储名称', function () {
    expect(Storage::getDefaultStorage())->toBe(Storage::MODE_LOCAL);
});

test('getConfig 可按名称取回存储配置', function () {
    $config = Storage::getConfig(Storage::MODE_LOCAL);

    expect($config)->toBeArray()->toHaveKey('adapter', LocalAdapter::class)->toHaveKey('algo', 'sha1');
});
