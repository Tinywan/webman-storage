<?php
/**
 * @desc StorageService
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 20:03
 */

declare(strict_types=1);

namespace Tinywan\Storage;

use support\Container;
use Tinywan\Storage\Exception\StorageException;

/**
 * @see Storage
 * @mixin Storage
 *
 * @method static uploadFile(array $config = []) 上传文件
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
    public static function config(string $storage = self::MODE_LOCAL)
    {
        $config = config('plugin.tinywan.storage.app.storage');
        if (!isset($config[$storage]) || empty($config[$storage]['adapter'])) {
            throw new StorageException('对应的adapter不存在');
        }
        static::$adapter = Container::make($config[$storage]['adapter'], []);
    }
}
