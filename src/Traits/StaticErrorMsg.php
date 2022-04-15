<?php
/**
 * @desc StaticErrorMsg.php 描述信息
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 19:29
 */
declare(strict_types=1);

namespace Tinywan\Storage\Traits;

trait StaticErrorMsg
{
    public static $message = 'success';

    /**
     * @desc: 设置错误消息
     *
     * @param bool   $success 是否成功
     * @param string $message 错误消息
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public static function setStaticError(bool $success, string $message): bool
    {
        self::$message = $message;

        return $success;
    }

    /**
     * @desc: 获取错误消息
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public static function getStaticMessage(): string
    {
        return self::$message;
    }
}
