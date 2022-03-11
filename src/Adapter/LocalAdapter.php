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
    public function upload(array $options = []): array
    {
        $result = [];
        foreach ($this->files as $key => $file) {
            $uniqueId = hash_file('md5', $file->getPathname());
            $saveFilename = $uniqueId.'.'.$file->getUploadExtension();
            $path = $this->directory.DIRECTORY_SEPARATOR.$saveFilename;
            $temp = [
                'key' => $key,
                'origin_name' => $file->getUploadName(),
                'save_name' => $saveFilename,
                'save_path' => $path,
                'unique_id' => $uniqueId,
                'size' => $file->getSize(),
                'mime_type' => $file->getUploadMineType(),
                'extension' => $file->getUploadExtension(),
            ];
            $file->move(public_path().$path);
            array_push($result, $temp);
        }

        return $result;
    }
}
