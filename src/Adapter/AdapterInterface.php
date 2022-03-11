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
     * @desc: 上传
     *
     * @return mixed
     */
    public function upload(array $options);
}
