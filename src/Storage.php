<?php
/**
 * @desc StorageService
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 20:03
 */
declare(strict_types=1);

namespace Tinywan\Storage;

use Tinywan\Storage\Exception\StorageException;

/**
 * @see Storage
 * @mixin Storage
 *
 * @method static array uploadFile(array $config = [])                          上传文件
 * @method static array uploadBase64(string $base64, string $extension = 'png') 上传Base64文件
 * @method static array uploadServerFile(string $file_path)                     上传服务端文件
 */
class Storage
{
    /**
     * 本地对象存储.
     */
    public const MODE_LOCAL = 'local';

    /**
     * 阿里云对象存储.
     */
    public const MODE_OSS = 'oss';

    /**
     * 腾讯云对象存储.
     */
    public const MODE_COS = 'cos';

    /**
     * 七牛云对象存储.
     */
    public const MODE_QINIU = 'qiniu';

    /**
     * @var
     */
    protected static $adapter = null;

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public static function __callStatic($name, $arguments)
    {
        return static::$adapter->{$name}(...$arguments);
    }

    /**
     * @desc: 方法描述
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public static function config(string $storage = null, bool $_is_file_upload = true)
    {
        $config = config('plugin.tinywan.storage.app.storage');
        $storage = $storage ?: $config['default'];
        if (!isset($config[$storage]) || empty($config[$storage]['adapter'])) {
            throw new StorageException('对应的adapter不存在');
        }
        static::$adapter = new $config[$storage]['adapter'](array_merge(
            $config[$storage],
            [
                '_is_file_upload' => $_is_file_upload,
            ]
        ));
    }
}
