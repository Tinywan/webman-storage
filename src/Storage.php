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
use Tinywan\Storage\Exception\StorageAdapterException;

/**
 * @see Storage
 * @mixin Storage
 *
 * @method static upload(array $config = []) 上传文件
 */
class Storage
{
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
    public static function config(string $storage = 'local')
    {
        $config = config('plugin.tinywan.storage.app.storage');
        if (!isset($config[$storage]) || empty($config[$storage]['adapter'])) {
            throw new StorageAdapterException('对应的adapter不存在');
        }
        static::$adapter = Container::make($config[$storage]['adapter'], []);
    }
}
