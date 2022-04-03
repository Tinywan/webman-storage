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
    protected static $instance = null;

    /**
     * @desc: 实例
     */
    public static function getInstance(): ?UploadManager
    {
        if (is_null(self::$instance)) {
            static::$instance = new UploadManager();
        }

        return static::$instance;
    }

    public static function getUploadToken(): string
    {
        $config = config('plugin.tinywan.storage.app.storage.qiniu');
        $auth = new Auth($config['accessKey'], $config['secretKey']);

        return $auth->uploadToken($config['bucket']);
    }

    /**
     * @desc: 方法描述
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadFile(array $options = []): array
    {
        try {
            $config = config('plugin.tinywan.storage.app.storage.qiniu');
            $result = [];
            foreach ($this->files as $key => $file) {
                $uniqueId = hash_file('sha1', $file->getPathname());
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
                list($ret, $err) = self::getInstance()->putFile(self::getUploadToken(), $object, $file->getPathname());
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

        $config = config('plugin.tinywan.storage.app.storage.qiniu');
        $uniqueId = hash_file('sha1', $file->getPathname()).date('YmdHis');
        $object = $config['dirname'].$this->dirSeparator.$uniqueId.'.'.$file->getExtension();

        $result = [
            'origin_name' => $file->getRealPath(),
            'save_path' => $object,
            'url' => $config['domain'].$this->dirSeparator.$object,
            'unique_id' => $uniqueId,
            'size' => $file->getSize(),
            'extension' => $file->getExtension()
        ];

        list($ret, $err) = self::getInstance()->putFile(self::getUploadToken(), $object, $file->getPathname());
        if (!empty($err)) {
            throw new StorageException((string) $err);
        }
        return $result;
    }

    /**
     * 上传Base64
     * @param string $base64
     * @param string $extension
     * @return array
     * @throws \Exception
     */
    public function uploadBase64(string $base64, string $extension = 'png')
    {
        $base64 = explode(',', $base64);
        $config = config('plugin.tinywan.storage.app.storage.qiniu');
        $uniqueId = date('YmdHis').uniqid();
        $object = $config['dirname'].$this->dirSeparator.$uniqueId.'.'.$extension;

        list($ret, $err) = self::getInstance()->put(self::getUploadToken(), $object, base64_decode($base64[1]));
        if (!empty($err)) {
            throw new StorageException((string) $err);
        }

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