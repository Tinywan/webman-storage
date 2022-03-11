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
        'directory' => 'upload', // 文件的存储路径,相对于public
        'single_limit' => 1024 * 1024 * 200, // 单个文件的大小限制，默认200M 1024 * 1024 * 200
        'total_limit' => 1024 * 1024 * 200, // 所有文件的大小限制，默认200M 1024 * 1024 * 200
        'nums' => 2, // 文件数量限制，默认10
        'include' => [], // 文件后缀名的排除项，默认排除[]，即允许所有类型的文件上传
        'exclude' => [], // 文件后缀名的包括项
        'local' => [
            'adapter' => \Tinywan\Storage\Adapter\LocalAdapter::class,
        ],
        'oss' => [
            'adapter' => \Tinywan\Storage\Adapter\OssAdapter::class,
            'accessKeyId' => 'xxxxxxxxxxxx',
            'accessKeySecret' => 'xxxxxxxxxxxx',
            'bucket' => 'test',
            'domain' => 'xxxxxxxxxxxx',
            'endpoint' => 'oss-cn.aliyuncs.com',
        ],
    ],
];
