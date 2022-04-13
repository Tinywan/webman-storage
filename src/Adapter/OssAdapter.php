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
    protected $instance = null;

    /**
     * @desc: 阿里雲实例
     *
     * @throws OssException
     */
    public function getInstance(): ?OssClient
    {
        if (is_null($this->instance)) {
            $this->instance = new OssClient(
                $this->config['accessKeyId'],
                $this->config['accessKeySecret'],
                $this->config['endpoint']
            );
        }

        return $this->instance;
    }

    /**
     * @desc: 方法描述
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadFile(array $options = []): array
    {
        try {
            $result = [];
            foreach ($this->files as $key => $file) {
                $uniqueId = hash_file('sha1', $file->getPathname()).date('YmdHis');
                $saveName = $uniqueId.'.'.$file->getUploadExtension();
                $object = $this->config['dirname'].$this->dirSeparator.$saveName;
                $temp = [
                    'key' => $key,
                    'origin_name' => $file->getUploadName(),
                    'save_name' => $saveName,
                    'save_path' => $object,
                    'url' => $this->config['domain'].$this->dirSeparator.$object,
                    'unique_id' => $uniqueId,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getUploadMineType(),
                    'extension' => $file->getUploadExtension(),
                ];
                $upload = $this->getInstance()->uploadFile($this->config['bucket'], $object, $file->getPathname());
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
        $bucket = $this->config['bucket'];
        $uniqueId = date('YmdHis').uniqid();
        $object = $this->config['dirname'].$this->dirSeparator.$uniqueId.'.'.$extension;

        try {
            $result = $this->getInstance()->putObject($bucket, $object, base64_decode($base64[1]));
            if (!isset($result['info']) && 200 != $result['info']['http_code']) {
                return $this->setError(false, (string) $result);
            }
        } catch (OssException $e) {
            return $this->setError(false, $e->getMessage());
        }
        $imgLen = strlen($base64['1']);
        $fileSize = $imgLen - ($imgLen / 8) * 2;

        return [
            'save_path' => $object,
            'url' => $this->config['domain'].$this->dirSeparator.$object,
            'unique_id' => $uniqueId,
            'size' => $fileSize,
            'extension' => $extension,
        ];
    }

    /**
     * @desc: 上传服务端文件
     *
     * @throws OssException
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadServerFile(string $file_path): array
    {
        $file = new \SplFileInfo($file_path);
        if (!$file->isFile()) {
            throw new StorageException('不是一个有效的文件');
        }

        $uniqueId = hash_file('sha1', $file->getPathname()).date('YmdHis');
        $object = $this->config['dirname'].$this->dirSeparator.$uniqueId.'.'.$file->getExtension();

        $result = [
            'origin_name' => $file->getRealPath(),
            'save_path' => $object,
            'url' => $this->config['domain'].$this->dirSeparator.$object,
            'unique_id' => $uniqueId,
            'size' => $file->getSize(),
            'extension' => $file->getExtension(),
        ];
        $upload = $this->getInstance()->uploadFile($this->config['bucket'], $object, $file->getRealPath());
        if (!isset($upload['info']) && 200 != $upload['info']['http_code']) {
            throw new StorageException((string) $upload);
        }

        return $result;
    }
}
