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
use Webman\Http\UploadFile;

class OssAdapter extends AdapterAbstract
{
    /**
     * @var OssClient|null
     */
    protected $instance = null;

    /**
     * @desc: 阿里雲实例
     *
     * @throws OssException
     */
    public function getInstance(): OssClient
    {
        if (is_null($this->instance)) {
            $this->instance = new OssClient(
                $this->config['accessKeyId'],
                $this->config['accessKeySecret'],
                $this->config['endpoint'],
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
                $upload = $this->getInstance()->uploadFile(
                    $this->config['bucket'],
                    $object,
                    $file->getPathname(),
                    $options,
                );
                if (!isset($upload['info']) && 200 !== $upload['info']['http_code']) {
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
        $uniqueId = date('YmdHis') . uniqid();
        $object = $this->config['dirname'] . $this->dirSeparator . $uniqueId . '.' . $extension;

        try {
            $result = $this->getInstance()->putObject($this->config['bucket'], $object, base64_decode($base64[1]));
            if (!isset($result['info']) && 200 !== $result['info']['http_code']) {
                return $this->setError(false, (string) $result);
            }
        } catch (OssException $e) {
            return $this->setError(false, $e->getMessage());
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
        $upload = $this->getInstance()->uploadFile($this->config['bucket'], $object, $file->getRealPath());
        if (!isset($upload['info']) && 200 !== $upload['info']['http_code']) {
            throw new StorageException((string) $upload);
        }

        return $result;
    }

    /**
     * @desc: 上传文件分片（原生 multipart），首次上传自动初始化会话，uploadId/ETag 记录于本地会话文件
     */
    public function uploadChunk(
        UploadFile $file,
        string $file_id,
        int $chunk_index,
        string $chunk_md5,
        array $options = [],
    ): array {
        $actualMd5 = $this->assertChunkUploadable($file, $file_id, $chunk_index, $chunk_md5);

        try {
            $session = $this->loadSession($file_id);
            if (empty($session['upload_id'])) {
                $object = $this->config['dirname'] . $this->dirSeparator . $file_id;
                $session = [
                    'upload_id' => (string) $this->getInstance()->initiateMultipartUpload(
                        $this->config['bucket'],
                        $object,
                    ),
                    'object' => $object,
                    'parts' => [],
                ];
            }
            $etag = (string) $this->getInstance()->uploadPart(
                $this->config['bucket'],
                $session['object'],
                $session['upload_id'],
                [
                    OssClient::OSS_FILE_UPLOAD => $file->getPathname(),
                    OssClient::OSS_PART_NUM => $chunk_index + 1,
                ],
            );
            $session = $this->withSessionPart($session, $chunk_index, [
                'etag' => $etag,
                'md5' => $actualMd5,
                'size' => $this->getSize($file),
            ]);
            $this->saveSession($file_id, $session);
        } catch (Throwable|OssException $exception) {
            throw new StorageException($exception->getMessage());
        }

        return [
            'file_id' => $file_id,
            'chunk_index' => $chunk_index,
            'save_name' => $this->chunkPartName($chunk_index),
            'save_path' => $session['object'],
            'size' => $this->getSize($file),
            'md5' => $actualMd5,
        ];
    }

    /**
     * @desc: 查询分片上传进度，用于断点续传
     */
    public function chunkProgress(string $file_id, int $total_chunks): array
    {
        return $this->sessionChunkProgress($file_id, $total_chunks);
    }

    /**
     * @desc: 合并全部分片（completeMultipartUpload），随后 copy 改名为最终对象并删除临时对象
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
        $session = $this->loadSession($file_id);
        $parts = $session['parts'] ?? [];
        $missing = $this->missingSessionParts($parts, $total_chunks);
        if ($missing) {
            throw new StorageException('缺少分片：' . implode(',', $missing));
        }
        if (empty($session['upload_id']) || empty($session['object'])) {
            throw new StorageException('分片会话不存在或已损坏');
        }
        $lock = $this->acquireMergeLock($file_id);

        try {
            $listParts = [];
            $size = 0;
            for ($i = 0; $i < $total_chunks; $i++) {
                $listParts[] = ['PartNumber' => $i + 1, 'ETag' => (string) $parts[$i]['etag']];
                $size += (int) ($parts[$i]['size'] ?? 0);
            }

            try {
                $this->getInstance()->completeMultipartUpload(
                    $this->config['bucket'],
                    $session['object'],
                    $session['upload_id'],
                    $listParts,
                );
                [$saveName, $extension, $uniqueId] = $this->chunkFinalName($file_id, $file_name, $options);
                $finalObject = $this->config['dirname'] . $this->dirSeparator . $saveName;
                $this->getInstance()->copyObject(
                    $this->config['bucket'],
                    $session['object'],
                    $this->config['bucket'],
                    $finalObject,
                );
                $this->getInstance()->deleteObject($this->config['bucket'], $session['object']);
                $this->removeDir($this->chunkDir($file_id));
            } catch (Throwable|OssException $exception) {
                throw new StorageException($exception->getMessage());
            }

            return [
                'origin_name' => $file_name,
                'save_name' => $saveName,
                'save_path' => $finalObject,
                'url' => $this->config['domain'] . $this->dirSeparator . $finalObject,
                'unique_id' => $uniqueId,
                'size' => $size,
                'mime_type' => (string) ($options['mime_type'] ?? 'application/octet-stream'),
                'extension' => $extension,
            ];
        } finally {
            $this->releaseMergeLock($lock);
        }
    }
}
