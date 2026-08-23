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
use Webman\Http\UploadFile;

class S3Adapter extends AdapterAbstract
{
    /**
     * @var S3Client|null
     */
    protected $instance = null;

    /**
     * @desc 对象存储实例
     * @author Tinywan(ShaoBo Wan)
     */
    public function getInstance(): S3Client
    {
        if (is_null($this->instance)) {
            $this->instance = new S3Client([
                'version' => $this->config['version'],
                'endpoint' => $this->config['endpoint'],
                'region' => $this->config['region'],
                'use_path_style_endpoint' => $this->config['use_path_style_endpoint'],
                'credentials' => [
                    'key' => $this->config['key'],
                    'secret' => $this->config['secret'],
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
                $this->getInstance()->putObject([
                    'Bucket' => $this->config['bucket'],
                    'Key' => $object,
                    'Body' => fopen($file->getPathname(), 'rb'),
                    'ACL' => $this->config['acl'],
                ]);
                $result[] = $temp;
            }
        } catch (Throwable $exception) {
            throw new StorageException($exception->getMessage());
        }

        return $result;
    }

    /**
     * @desc 上传服务端文件
     * @param string $file_path
     * @return array
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

        $this->getInstance()->putObject([
            'Bucket' => $this->config['bucket'],
            'Key' => $object,
            'Body' => fopen($file->getPathname(), 'rb'),
            'ACL' => $this->config['acl'],
        ]);

        return $result;
    }

    /**
     * @desc: 上传Base64
     * @param string $base64
     * @param string $extension
     * @return array
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadBase64(string $base64, string $extension = 'png'): array
    {
        $base64 = explode(',', $base64);
        $uniqueId = date('YmdHis') . uniqid();
        $object = $this->config['dirname'] . $this->dirSeparator . $uniqueId . '.' . $extension;

        $this->getInstance()->putObject([
            'Bucket' => $this->config['bucket'],
            'Key' => $object,
            'Body' => base64_decode($base64[1]),
            'ACL' => $this->config['acl'],
        ]);

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
                $init = $this->getInstance()->createMultipartUpload([
                    'Bucket' => $this->config['bucket'],
                    'Key' => $object,
                ]);
                $session = ['upload_id' => (string) $init['UploadId'], 'object' => $object, 'parts' => []];
            }
            $part = $this->getInstance()->uploadPart([
                'Bucket' => $this->config['bucket'],
                'Key' => $session['object'],
                'UploadId' => $session['upload_id'],
                'PartNumber' => $chunk_index + 1,
                'Body' => fopen($file->getPathname(), 'rb'),
            ]);
            $session = $this->withSessionPart($session, $chunk_index, [
                'etag' => (string) $part['ETag'],
                'md5' => $actualMd5,
                'size' => $this->getSize($file),
            ]);
            $this->saveSession($file_id, $session);
        } catch (Throwable $exception) {
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
     * @desc: 合并全部分片（CompleteMultipartUpload），随后 CopyObject 改名为最终对象并删除临时对象
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
                $this->getInstance()->completeMultipartUpload([
                    'Bucket' => $this->config['bucket'],
                    'Key' => $session['object'],
                    'UploadId' => $session['upload_id'],
                    'MultipartUpload' => ['Parts' => $listParts],
                ]);
                [$saveName, $extension, $uniqueId] = $this->chunkFinalName($file_id, $file_name, $options);
                $finalObject = $this->config['dirname'] . $this->dirSeparator . $saveName;
                $this->getInstance()->copyObject([
                    'Bucket' => $this->config['bucket'],
                    'Key' => $finalObject,
                    'CopySource' => $this->config['bucket'] . '/' . $session['object'],
                    'ACL' => $this->config['acl'],
                ]);
                $this->getInstance()->deleteObject([
                    'Bucket' => $this->config['bucket'],
                    'Key' => $session['object'],
                ]);
                $this->removeDir($this->chunkDir($file_id));
            } catch (Throwable $exception) {
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
