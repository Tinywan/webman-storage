<?php
/**
 * @desc app.php 描述信息
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/10 19:46
 */

return [
    'enable' => true,
    'storage' => [
        'default' => 'local', // local：本地 oss：阿里云 cos：腾讯云 qos：七牛云
        'single_limit' => 1024 * 1024 * 200, // 单个文件的大小限制，默认200M 1024 * 1024 * 200
        'total_limit' => 1024 * 1024 * 200, // 所有文件的大小限制，默认200M 1024 * 1024 * 200
        'nums' => 10, // 文件数量限制，默认10
        'chunk_size' => 1024 * 1024 * 5, // 分片上传：单个分片的大小限制，默认5M
        'chunk_path' => runtime_path() . '/storage/.chunks', // 分片上传：分片暂存/会话目录（云端适配器必需），默认 {root}/.chunks
        'include' => [], // 被允许的文件类型列表
        'exclude' => [], // 不被允许的文件类型列表
        // 本地对象存储
        'local' => [
            'adapter' => \Tinywan\Storage\Adapter\LocalAdapter::class,
            'root' => public_path() . '/storage',
            'dirname' => function () {
                return date('Ymd');
            },
            'domain' => 'http://127.0.0.1:8787',
            // uri 必须与 root 相对 public 目录的路径一致（此处 root 为 public/storage），否则生成的 url 无法访问
            'uri' => '/storage',
            'algo' => 'sha1',
            'chunk_path' => runtime_path() . '/storage/.chunks', // 分片上传：分片临时存储目录
        ],
        // 阿里云对象存储
        'oss' => [
            'adapter' => \Tinywan\Storage\Adapter\OssAdapter::class,
            'accessKeyId' => 'xxxxxxxxxxxx',
            'accessKeySecret' => 'xxxxxxxxxxxx',
            'bucket' => 'resty-webman',
            'dirname' => function () {
                return 'storage';
            },
            'domain' => 'http://webman.oss.tinywan.com',
            'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
            'algo' => 'sha1',
        ],
        // 腾讯云对象存储
        'cos' => [
            'adapter' => \Tinywan\Storage\Adapter\CosAdapter::class,
            'secretId' => 'xxxxxxxxxxxxx',
            'secretKey' => 'xxxxxxxxxxxx',
            'bucket' => 'resty-webman-xxxxxxxxx',
            'dirname' => 'storage',
            'domain' => 'http://webman.oss.tinywan.com',
            'region' => 'ap-shanghai',
        ],
        // 七牛云对象存储
        'qiniu' => [
            'adapter' => \Tinywan\Storage\Adapter\QiniuAdapter::class,
            'accessKey' => 'xxxxxxxxxxxxx',
            'secretKey' => 'xxxxxxxxxxxxx',
            'bucket' => 'resty-webman',
            'dirname' => 'storage',
            'domain' => 'http://webman.oss.tinywan.com',
        ],
        // aws
        's3' => [
            'adapter' => \Tinywan\Storage\Adapter\S3Adapter::class,
            'key' => 'xxxxxxxxxxxxx',
            'secret' => 'xxxxxxxxxxxxx',
            'bucket' => 'resty-webman',
            'dirname' => 'storage',
            'domain' => 'http://webman.oss.tinywan.com',
            'region' => 'S3_REGION',
            'version' => 'latest',
            'use_path_style_endpoint' => true,
            'endpoint' => 'S3_ENDPOINT',
            'acl' => 'public-read',
        ],
    ],
];
