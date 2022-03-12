<?php

/**
 * @desc 阿里云OSS适配器
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 19:54
 */

declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

use OSS\Core\OssException;
use OSS\OssClient;
use Throwable;
use Tinywan\Storage\Exception\StorageException;

class OssAdapter extends AdapterAbstract
{
    protected static ?OssClient $instance = null;

    /**
     * @desc: 阿里雲实例
     *
     * @throws OssException
     */
    public static function getInstance(): ?OssClient
    {
        if (is_null(self::$instance)) {
            $config = config('plugin.tinywan.storage.app.storage.oss');
            static::$instance = new OssClient(
                $config['accessKeyId'],
                $config['accessKeySecret'],
                $config['endpoint']
            );
        }

        return static::$instance;
    }

    /**
     * @desc: 方法描述
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadFile(array $options = []): array
    {
        try {
            $config = config('plugin.tinywan.storage.app.storage.oss');
            $result = [];
            foreach ($this->files as $key => $file) {
                $uniqueId = hash_file('md5', $file->getPathname());
                $saveName = $uniqueId.'.'.$file->getUploadExtension();
                $object = $config['dirname'].DIRECTORY_SEPARATOR.$saveName;
                $temp = [
                    'key' => $key,
                    'origin_name' => $file->getUploadName(),
                    'save_name' => $saveName,
                    'save_path' => $object,
                    'url' => $config['domain'].$object,
                    'unique_id' => $uniqueId,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getUploadMineType(),
                    'extension' => $file->getUploadExtension(),
                ];
                $upload = self::getInstance()->uploadFile($config['bucket'], $object, $file->getPathname());
                if (!isset($upload['info']) && 200 != $upload['info']['http_code']) {
                    throw new StorageException((string) $upload);
                }
                array_push($result, $temp);
            }
        } catch (Throwable|OssException $exception) {
            throw new StorageException($exception->getMessage());
        }

        return $result;
    }
}
