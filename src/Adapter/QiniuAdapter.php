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
use Webman\Http\UploadFile;

class QiniuAdapter extends AdapterAbstract
{
    /**
     * @var UploadManager|null
     */
    protected $instance = null;

    /**
     * @var string|null
     */
    protected $uploadToken = null;

    /**
     * 合并时流式读写的缓冲区大小：256KB.
     */
    private const MERGE_BUFFER_SIZE = 262144;

    /**
     * @desc: 实例
     */
    public function getInstance(): UploadManager
    {
        if (!$this->instance) {
            $this->instance = new UploadManager();
        }

        return $this->instance;
    }

    /**
     * @desc: 获取上传令牌
     * @return string
     * @author Tinywan(ShaoBo Wan)
     */
    public function getUploadToken(): string
    {
        if (!$this->uploadToken) {
            $auth = new Auth($this->config['accessKey'], $this->config['secretKey']);
            $this->uploadToken = (string) $auth->uploadToken($this->config['bucket']);
        }

        return $this->uploadToken;
    }

    /**
     * @desc: 上传文件
     * @param array $options
     * @return array
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadFile(array $options = []): array
    {
        try {
            $result = [];
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
                    'mime_type' => $file->getUploadMimeType(),
                    'extension' => $file->getUploadExtension(),
                ];
                list($ret, $err) = $this->getInstance()->putFile(
                    $this->getUploadToken(),
                    $object,
                    $file->getPathname(),
                );
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
        $object = $this->config['dirname'] . $this->dirSeparator . $uniqueId . '.' . $file->getExtension();

        $result = [
            'origin_name' => $file->getRealPath(),
            'save_path' => $object,
            'url' => $this->config['domain'] . $this->dirSeparator . $object,
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
        $uniqueId = date('YmdHis') . uniqid();
        $object = $this->config['dirname'] . $this->dirSeparator . $uniqueId . '.' . $extension;

        list($ret, $err) = $this->getInstance()->put($this->getUploadToken(), $object, base64_decode($base64[1]));
        if (!empty($err)) {
            throw new StorageException((string) $err);
        }

        $imgLen = strlen($base64[1]);
        $fileSize = $imgLen - (($imgLen / 8) * 2);

        return [
            'save_path' => $object,
            'url' => $this->config['domain'] . $this->dirSeparator . $object,
            'unique_id' => $uniqueId,
            'size' => $fileSize,
            'extension' => $extension,
        ];
    }

    /**
     * @desc: 上传文件分片。七牛 SDK 无公开分片 API，分片先本地暂存，合并时经 putFile 上传（SDK 自动断点续传）
     */
    public function uploadChunk(
        UploadFile $file,
        string $file_id,
        int $chunk_index,
        string $chunk_md5,
        array $options = [],
    ): array {
        $actualMd5 = $this->assertChunkUploadable($file, $file_id, $chunk_index, $chunk_md5);

        return $this->saveStagedChunk($file, $file_id, $chunk_index, $actualMd5);
    }

    /**
     * @desc: 查询分片上传进度，用于断点续传
     */
    public function chunkProgress(string $file_id, int $total_chunks): array
    {
        return $this->stagedChunkProgress($file_id, $total_chunks);
    }

    /**
     * @desc: 合并全部分片：本地流式拼接后经 putFile 上传（大文件 SDK 自动走断点续传），成功后清理暂存
     */
    public function mergeChunk(string $file_id, int $total_chunks, string $file_name, array $options = []): array
    {
        $this->assertValidFileId($file_id);
        if ($total_chunks <= 0) {
            throw new StorageException('分片总数不合法：' . $total_chunks);
        }
        if (!$this->allowedExtension($file_name)) {
            throw new StorageException($file_name . '，文件扩展名不合法');
        }
        $missing = [];
        for ($i = 0; $i < $total_chunks; $i++) {
            if (!is_file($this->chunkPartPath($file_id, $i))) {
                $missing[] = $i;
            }
        }
        if ($missing) {
            throw new StorageException('缺少分片：' . implode(',', $missing));
        }
        $lock = $this->acquireMergeLock($file_id);

        try {
            $tmpPath = $this->chunkDir($file_id) . '.merging.part';
            $target = @fopen($tmpPath, 'wb');
            if (false === $target) {
                throw new StorageException('合并文件创建失败');
            }

            try {
                for ($i = 0; $i < $total_chunks; $i++) {
                    $part = @fopen($this->chunkPartPath($file_id, $i), 'rb');
                    if (false === $part) {
                        throw new StorageException('分片读取失败：' . $i);
                    }
                    while (!feof($part)) {
                        $buffer = fread($part, self::MERGE_BUFFER_SIZE);
                        if (false === $buffer || '' === $buffer) {
                            break;
                        }
                        fwrite($target, $buffer);
                    }
                    fclose($part);
                }
            } finally {
                fclose($target);
            }

            [$saveName, $extension, $uniqueId] = $this->chunkFinalName($file_id, $file_name, $options);
            $object = $this->config['dirname'] . $this->dirSeparator . $saveName;
            $size = (int) filesize($tmpPath);

            try {
                $this->uploadMergedFile($object, $tmpPath);
            } finally {
                @unlink($tmpPath);
            }
            $this->removeDir($this->chunkDir($file_id));

            return [
                'origin_name' => $file_name,
                'save_name' => $saveName,
                'save_path' => $object,
                'url' => $this->config['domain'] . $this->dirSeparator . $object,
                'unique_id' => $uniqueId,
                'size' => $size,
                'mime_type' => (string) ($options['mime_type'] ?? 'application/octet-stream'),
                'extension' => $extension,
            ];
        } finally {
            $this->releaseMergeLock($lock);
        }
    }

    /**
     * @desc: 上传合并产物，putFile 对大文件自动切换断点续传
     */
    protected function uploadMergedFile(string $object, string $file_path): void
    {
        list($ret, $err) = $this->getInstance()->putFile($this->getUploadToken(), $object, $file_path);
        if (!empty($err)) {
            throw new StorageException((string) $err);
        }
    }
}
