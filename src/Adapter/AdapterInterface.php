<?php
/**
 * @desc AdapterInterface 适配器接口
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/9 10:07
 */

declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

interface AdapterInterface
{
    /**
     * @desc: 上传文件
     *
     * @return mixed
     */
    public function uploadFile(array $options);

    /**
     * @desc: 上传服务端文件
     *
     * @return mixed
     */
    public function uploadServerFile(array $options);

    /**
     * @desc: Base64上传文件
     *
     * @return mixed
     */
    public function uploadBase64(array $options);
}
