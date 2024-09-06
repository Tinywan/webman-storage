<?php
/**
 * @desc AWS 对象存储适配器
 * @author Tinywan(ShaoBo Wan)
 * @date 2024/9/13 19:54
 */
declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

use Aws\S3\S3Client;
use Throwable;
use Tinywan\Storage\Exception\StorageException;

class S3Adapter extends AdapterAbstract
{
    /**
     * @var null
     */
    protected $instance = null;

    /**
     * @desc 对象存储实例
     * @author Tinywan(ShaoBo Wan)
     */
    public function getInstance(): ?S3Client
    {
        if (is_null($this->instance)) {
            $this->instance = new S3Client([
                'version' => 'latest',
                'region' => $this->config['region'],
                'use_path_style_endpoint' => $this->config['use_path_style_endpoint'],
                'credentials' => [
                    'key' => $this->config['secretId'],
                    'secret' => $this->config['secretKey'],
                ],
            ]);
        }

        return $this->instance;
    }

    /**
     * @desc 上传文件
     * @param array $options
     * @return array
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadFile(array $options = []): array
    {
        try {
            $result = [];
//            $options = [
//                'ACL'           => 'public-read', // 访问控制列表
//                'Metadata'      => [
//                    'my-key' => 'my-value', // 自定义元数据
//                ],
//                'StorageClass'  => 'STANDARD', // 存储类
//                'ContentType'   => 'text/plain', // MIME 类型
//                'ServerSideEncryption' => 'AES256', // 服务器端加密
//                'CacheControl'  => 'max-age=3600', // 缓存控制
//            ];
            foreach ($this->files as $key => $file) {
                $uniqueId = hash_file($this->algo, $file->getPathname());
                $saveName = $uniqueId . '.' . $file->getUploadExtension();
                $object = $this->config['dirname'] . $this->dirSeparator . $saveName;
                $temp = [
                    'key' => $key,
                    'origin_name' => $file->getUploadName(),
                    'save_name' => $saveName,
                    'save_path' => $object,
                    'url' => $this->config['domain'] . $this->dirSeparator . $object,
                    'unique_id' => $uniqueId,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getUploadMineType(),
                    'extension' => $file->getUploadExtension(),
                ];
                $this->getInstance()->putObject([
                    'Bucket' => $this->config['bucket'],
                    'Key' => $object,
                    'Body' => fopen($file->getPathname(), 'rb'),
                    'SourceFile' => $file->getPathname()
                ]);
                $result[] = $temp;
            }
        } catch (Throwable $exception) {
            throw new StorageException($exception->getMessage());
        }

        return $result;
    }

}
