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
use Tinywan\Storage\Exception\StorageAdapterException;

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
    public function upload(array $options = []): array
    {
        try {
            $config = config('plugin.tinywan.storage.app.storage.oss');
            $result = [];
            $bucket = $config['bucket'];
            $domain = $config['domain'];
            foreach ($this->files as $key => $file) {
                $uniqueId = hash_file('md5', $file->getPathname());
                $object = 'static'.DIRECTORY_SEPARATOR.$uniqueId.'.'.$file->getUploadExtension();
                $temp = [
                    'key' => $key,
                    'origin_name' => $file->getUploadName(),
                    'save_name' => $object,
                    'save_path' => $domain.$object,
                    'unique_id' => $uniqueId,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getUploadMineType(),
                    'extension' => $file->getUploadExtension(),
                ];
                $upload = self::getInstance()->uploadFile($bucket, $object, $file->getPathname());
                if (!isset($upload['info']) && 200 != $upload['info']['http_code']) {
                    throw new StorageAdapterException((string) $upload);
                }
                array_push($result, $temp);
            }
        } catch (Throwable|OssException $exception) {
            throw new StorageAdapterException($exception->getMessage());
        }

        return $result;
    }
}
