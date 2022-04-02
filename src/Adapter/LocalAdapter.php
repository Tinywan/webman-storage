<?php

/**
 * @desc 本地适配器
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 19:54
 */

declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

class LocalAdapter extends AdapterAbstract
{
    /**
     * @desc: 方法描述
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadFile(array $options = []): array
    {
        $result = [];
        $config = config('plugin.tinywan.storage.app.storage.local');
        foreach ($this->files as $key => $file) {
            $uniqueId = hash_file('md5', $file->getPathname());
            $saveFilename = $uniqueId.'.'.$file->getUploadExtension();
            $savePath = $config['root'].$this->dirSeparator.$saveFilename;
            $temp = [
                'key' => $key,
                'origin_name' => $file->getUploadName(),
                'save_name' => $saveFilename,
                'save_path' => $savePath,
                'url' => $config['domain'].$config['dirname'].$this->dirSeparator.$saveFilename,
                'unique_id' => $uniqueId,
                'size' => $file->getSize(),
                'mime_type' => $file->getUploadMineType(),
                'extension' => $file->getUploadExtension(),
            ];
            $file->move($savePath);
            array_push($result, $temp);
        }

        return $result;
    }

    /**
     * @desc: 方法描述
     *
     * @return array
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadServerFile(array $options)
    {
        return [];
    }

    public function uploadBase64(array $options)
    {
        return [];
    }
}
