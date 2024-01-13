<?php
/**
 * @desc StorageService
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 20:03
 */
declare(strict_types=1);

namespace Tinywan\Storage;

/**
 * @see Storage
 * @mixin Storage
 *
 * @method static array uploadFile(array $config = [])  上传文件
 * @method static array uploadBase64(string $base64, string $extension = 'png') 上传Base64文件
 * @method static array uploadServerFile(string $file_path)  上传服务端文件
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
     * Support Storage
     */
    static $allowStorage = [
        self::MODE_LOCAL,
        self::MODE_OSS,
        self::MODE_COS,
        self::MODE_QINIU
    ];

    /**
     * @var
     */
    protected static $adapter = null;

    /**
     * @desc config
     * @param string $storage
     * @param bool $_is_file_upload
     * @return mixed
     * @author Tinywan(ShaoBo Wan)
     */
    public static function config(string $storage = self::MODE_LOCAL, bool $_is_file_upload = true)
    {
        $storage = $storage ?? self::getDefaultStorage();
        if (isset(static::$adapter[$storage])) {
            return static::$adapter[$storage];
        }
        $config = self::getConfig($storage);
        static::$adapter[$storage] = new $config[$storage]['adapter'](array_merge(
            $config[$storage], ['_is_file_upload' => $_is_file_upload]
        ));
        return static::$adapter[$storage];
    }

    /**
     * @desc: 默认存储
     * @return mixed
     * @author Tinywan(ShaoBo Wan)
     */
    public static function getDefaultStorage()
    {
        return self::getConfig('default');
    }

    /**
     * @desc: 获取存储配置
     * @param string|null $name 名称
     * @param null $default 默认值
     * @return mixed
     * @author Tinywan(ShaoBo Wan)
     */
    public static function getConfig(string $name = null, $default = null)
    {
        if (!is_null($name)) {
            return config('plugin.tinywan.storage.app.storage.' . $name, $default);
        }
        return config('plugin.tinywan.storage.app.storage.default');
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @author Tinywan(ShaoBo Wan)
     */
    public static function __callStatic($name, $arguments)
    {
        return static::config()->{$name}(...$arguments);
    }
}
