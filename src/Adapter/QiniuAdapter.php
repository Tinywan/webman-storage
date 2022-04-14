<?php
/**
 * @desc 七牛云OSS适配器
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 19:54
 */
declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Throwable;
use Tinywan\Storage\Exception\StorageException;

class QiniuAdapter extends AdapterAbstract
{
    protected $instance = null;

    protected $uploadToken;

    /**
     * @desc: 实例
     */
    public function getInstance(): ?UploadManager
    {
        if (is_null($this->instance)) {
            $this->instance = new UploadManager();
        }

        return $this->instance;
    }

    /**
     * @desc: getUploadToken 描述
     */
    public function getUploadToken(): string
    {
        if ($this->uploadToken) {
            $auth = new Auth($this->config['accessKey'], $this->config['secretKey']);
            $this->uploadToken = $auth->uploadToken($this->config['bucket']);
        }

        return $this->uploadToken;
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
                $uniqueId = hash_file($this->algo, $file->getPathname());
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
                list($ret, $err) = $this->getInstance()->putFile($this->getUploadToken(), $object, $file->getPathname());
                if (!empty($err)) {
                    throw new StorageException((string) $err);
                }
                array_push($result, $temp);
            }
        } catch (Throwable $exception) {
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

        $uniqueId = hash_file($this->algo, $file->getPathname());
        $object = $this->config['dirname'].$this->dirSeparator.$uniqueId.'.'.$file->getExtension();

        $result = [
            'origin_name' => $file->getRealPath(),
            'save_path' => $object,
            'url' => $this->config['domain'].$this->dirSeparator.$object,
            'unique_id' => $uniqueId,
            'size' => $file->getSize(),
            'extension' => $file->getExtension(),
        ];

        list($ret, $err) = $this->getInstance()->putFile($this->getUploadToken(), $object, $file->getPathname());
        if (!empty($err)) {
            throw new StorageException((string) $err);
        }

        return $result;
    }

    /**
     * 上传Base64.
     */
    public function uploadBase64(string $base64, string $extension = 'png'): array
    {
        $base64 = explode(',', $base64);
        $uniqueId = date('YmdHis').uniqid();
        $object = $this->config['dirname'].$this->dirSeparator.$uniqueId.'.'.$extension;

        list($ret, $err) = $this->getInstance()->put($this->getUploadToken(), $object, base64_decode($base64[1]));
        if (!empty($err)) {
            throw new StorageException((string) $err);
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
}
