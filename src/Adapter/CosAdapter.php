<?php
/**
 * @desc 腾讯云对象存储适配器
 * @help https://cloud.tencent.com/document/product/436
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/13 19:54
 */
declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

use Qcloud\Cos\Client;
use Qcloud\Cos\Exception\CosException;
use Throwable;
use Tinywan\Storage\Exception\StorageException;

class CosAdapter extends AdapterAbstract
{
    /**
     * @var null
     */
    protected static $instance = null;

    /**
     * @desc: 对象存储实例
     */
    public static function getInstance(): ?Client
    {
        if (is_null(self::$instance)) {
            $config = config('plugin.tinywan.storage.app.storage.cos');
            static::$instance = new Client([
                'region' => $config['region'],
                'schema' => 'https',
                'credentials' => [
                    'secretId' => $config['secretId'],
                    'secretKey' => $config['secretKey'],
                ],
            ]);
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
            $config = config('plugin.tinywan.storage.app.storage.cos');
            $result = [];
            foreach ($this->files as $key => $file) {
                $uniqueId = hash_file('md5', $file->getPathname()).date('YmdHis');
                $saveName = $uniqueId.'.'.$file->getUploadExtension();
                $object = $config['dirname'].$this->dirSeparator.$saveName;
                $temp = [
                    'key' => $key,
                    'origin_name' => $file->getUploadName(),
                    'save_name' => $saveName,
                    'save_path' => $object,
                    'url' => $config['domain'].$this->dirSeparator.$object,
                    'unique_id' => $uniqueId,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getUploadMineType(),
                    'extension' => $file->getUploadExtension(),
                ];
                self::getInstance()->putObject([
                    'Bucket' => $config['bucket'],
                    'Key' => $object,
                    'Body' => fopen($file->getPathname(), 'rb'),
                ]);
                array_push($result, $temp);
            }
        } catch (Throwable|CosException $exception) {
            throw new StorageException($exception->getMessage());
        }

        return $result;
    }

    /**
     * @desc: 上传服务端文件
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadServerFile(string $file_path): array
    {
        $file = new \SplFileInfo($file_path);
        if (!$file->isFile()) {
            throw new StorageException('不是一个有效的文件');
        }

        $config = config('plugin.tinywan.storage.app.storage.cos');
        $uniqueId = hash_file('sha256', $file->getPathname()).date('YmdHis');
        $object = $config['dirname'].$this->dirSeparator.$uniqueId.'.'.$file->getExtension();

        $result = [
            'origin_name' => $file->getRealPath(),
            'save_path' => $object,
            'url' => $config['domain'].$this->dirSeparator.$object,
            'unique_id' => $uniqueId,
            'size' => $file->getSize(),
            'extension' => $file->getExtension(),
        ];

        self::getInstance()->putObject([
            'Bucket' => $config['bucket'],
            'Key' => $object,
            'Body' => fopen($file->getPathname(), 'rb'),
        ]);

        return $result;
    }

    /**
     * @desc: 上传Base64
     *
     * @return array|bool
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadBase64(string $base64, string $extension = 'png')
    {
        $base64 = explode(',', $base64);
        $config = config('plugin.tinywan.storage.app.storage.cos');
        $uniqueId = date('YmdHis').uniqid();
        $object = $config['dirname'].$this->dirSeparator.$uniqueId.'.'.$extension;

        self::getInstance()->putObject([
            'Bucket' => $config['bucket'],
            'Key' => $object,
            'Body' => base64_decode($base64[1]),
        ]);

        $imgLen = strlen($base64['1']);
        $fileSize = $imgLen - ($imgLen / 8) * 2;

        return [
            'save_path' => $object,
            'url' => $config['domain'].$this->dirSeparator.$object,
            'unique_id' => $uniqueId,
            'size' => $fileSize,
            'extension' => $extension,
        ];
    }
}
