<?php
/**
 * @desc 本地适配器
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 19:54
 */
declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

use Tinywan\Storage\Exception\StorageException;
use Webman\Http\UploadFile;

class LocalAdapter extends AdapterAbstract
{
    /**
     * 合并时流式读写的缓冲区大小：256KB.
     */
    private const MERGE_BUFFER_SIZE = 262144;

    /**
     * @desc: 方法描述
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadFile(array $options = []): array
    {
        $result = [];
        // root 末尾分隔符不确定，统一规整后再拼接日期目录，避免出现 storage20260823 这类错误目录
        $basePath = $this->basePath();
        if (!$this->createDir($basePath)) {
            throw new StorageException('文件夹创建失败，请核查是否有对应权限。');
        }

        $baseUrl = $this->baseUrl();
        foreach ($this->files as $key => $file) {
            $uniqueId = hash_file($this->algo, $file->getPathname());
            $saveFilename = $uniqueId . '.' . $file->getUploadExtension();
            $savePath = $basePath . $saveFilename;
            $temp = [
                'key' => $key,
                'origin_name' => $file->getUploadName(),
                'save_name' => $saveFilename,
                'save_path' => $savePath,
                'url' => $baseUrl . $saveFilename,
                'unique_id' => $uniqueId,
                'size' => $file->getSize(),
                'mime_type' => $file->getUploadMimeType(),
                'extension' => $file->getUploadExtension(),
            ];
            $file->move($savePath);
            array_push($result, $temp);
        }

        return $result;
    }

    /**
     * @desc: 上传文件分片，上传前校验分片 md5，同一 chunk_index 重复上传将覆盖（断点续传语义）
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
     * @desc: 合并全部分片，流式写入并增量计算文件散列，合并成功后清理分片临时目录
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
            $hashContext = hash_init($this->algo);
            $size = 0;

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
                        hash_update($hashContext, $buffer);
                        fwrite($target, $buffer);
                        $size += strlen($buffer);
                    }
                    fclose($part);
                }
            } finally {
                fclose($target);
            }

            $uniqueId = (string) hash_final($hashContext);
            $extension = $this->extensionOf($file_name);
            $basePath = $this->basePath();
            if (!$this->createDir($basePath)) {
                throw new StorageException('文件夹创建失败，请核查是否有对应权限。');
            }
            $saveFilename = $uniqueId . '.' . $extension;
            $savePath = $basePath . $saveFilename;
            if (is_file($savePath)) {
                unlink($savePath);
            }
            if (!rename($tmpPath, $savePath)) {
                throw new StorageException('合并文件保存失败');
            }
            $this->removeDir($this->chunkDir($file_id));
            $mimeType = function_exists('mime_content_type') ? mime_content_type($savePath) : false;

            return [
                'origin_name' => $file_name,
                'save_name' => $saveFilename,
                'save_path' => $savePath,
                'url' => $this->baseUrl() . $saveFilename,
                'unique_id' => $uniqueId,
                'size' => $size,
                'mime_type' => $mimeType ?: 'application/octet-stream',
                'extension' => $extension,
            ];
        } finally {
            $this->releaseMergeLock($lock);
        }
    }

    /**
     * @desc: 文件落盘根目录（root + 日期目录），规整分隔符避免目录粘连
     */
    protected function basePath(): string
    {
        return (
            rtrim($this->config['root'], '/\\') . DIRECTORY_SEPARATOR . $this->config['dirname'] . DIRECTORY_SEPARATOR
        );
    }

    /**
     * @desc: 访问 URL 前缀（domain + uri + 日期目录）
     */
    protected function baseUrl(): string
    {
        return (
            $this->config['domain']
            . rtrim($this->config['uri'], '/')
            . '/'
            . str_replace(DIRECTORY_SEPARATOR, '/', $this->config['dirname'])
            . DIRECTORY_SEPARATOR
        );
    }
}
