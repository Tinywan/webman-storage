<?php
/**
 * @desc 本地适配器
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 19:54
 */
declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

use Tinywan\Storage\Exception\StorageException;

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
        $basePath = $this->config['root'].$this->dirname.DIRECTORY_SEPARATOR;
        if (!$this->createDir($basePath)) {
            throw new StorageException('文件夹创建失败，请核查是否有对应权限。');
        }
        $baseUrl = $this->config['domain'].$this->config['uri'].str_replace(DIRECTORY_SEPARATOR, '/', $this->dirname).'/';
        foreach ($this->files as $key => $file) {
            $uniqueId = hash_file('sha1', $file->getPathname());
            $saveFilename = $uniqueId.'.'.$file->getUploadExtension();
            $savePath = $basePath.$saveFilename;
            $temp = [
                'key' => $key,
                'origin_name' => $file->getUploadName(),
                'save_name' => $saveFilename,
                'save_path' => $savePath,
                'url' => $baseUrl.$saveFilename,
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
     * @desc: createDir 描述
     *
     * @author Tinywan(ShaoBo Wan)
     */
    protected function createDir(string $path): bool
    {
        // 判断传过来的$path是否已是目录，若是，则直接返回true
        if (is_dir($path)) {
            return true;
        }

        // 走到这步，说明传过来的$path不是目录
        // 判断其上级是否为目录，否，则创建上级目录
        $parent = dirname($path);
        if (!is_dir($parent)) {
            // 创建上级目录
            if (!$this->createDir($parent)) {
                // 创建失败，返回 false
                return false;
            }
        }

        // 走到这步，说明上级目录已创建成功，则直接接着创建当前目录，并把创建的结果返回
        return mkdir($path);
    }
}
