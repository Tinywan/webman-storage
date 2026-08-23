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
     * 默认分片大小：5M.
     */
    public const DEFAULT_CHUNK_SIZE = 5242880;

    /**
     * file_id 白名单规则：字母或数字开头，仅允许字母、数字、下划线、中划线，长度不超过64，天然免疫路径穿越.
     */
    public const FILE_ID_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/';

    /**
     * 合并时流式读写的缓冲区大小：256KB.
     */
    private const MERGE_BUFFER_SIZE = 262144;

    /**
     * 单个分片的最大字节数.
     *
     * @var int
     */
    protected $chunkSize;

    /**
     * 分片临时存储目录.
     *
     * @var string
     */
    protected $chunkPath;

    /**
     * @desc: 方法描述
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadFile(array $options = []): array
    {
        $result = [];
        $basePath = $this->config['root'] . $this->config['dirname'] . DIRECTORY_SEPARATOR;
        if (!$this->createDir($basePath)) {
            throw new StorageException('文件夹创建失败，请核查是否有对应权限。');
        }

        $baseUrl =
            $this->config['domain']
            . $this->config['uri']
            . str_replace(DIRECTORY_SEPARATOR, '/', $this->config['dirname'])
            . DIRECTORY_SEPARATOR;
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
        $this->assertValidFileId($file_id);
        if ($chunk_index < 0) {
            throw new StorageException('分片序号不合法：' . $chunk_index);
        }
        if ($this->getSize($file) > $this->chunkSize) {
            throw new StorageException('分片大小超出限制：' . $this->chunkSize);
        }
        $actualMd5 = (string) md5_file($file->getPathname());
        if (!hash_equals($actualMd5, strtolower($chunk_md5))) {
            throw new StorageException('分片 md5 校验失败：' . $chunk_index);
        }
        $dir = $this->chunkDir($file_id);
        if (!$this->createDir($dir)) {
            throw new StorageException('分片目录创建失败，请核查是否有对应权限。');
        }
        $saveName = sprintf('%08d.part', $chunk_index);
        $savePath = $dir . DIRECTORY_SEPARATOR . $saveName;
        // Windows 下 move/rename 无法覆盖已存在文件，重传分片需先删除
        if (is_file($savePath)) {
            unlink($savePath);
        }
        $file->move($savePath);
        if (!is_file($savePath)) {
            throw new StorageException('分片保存失败：' . $chunk_index);
        }

        return [
            'file_id' => $file_id,
            'chunk_index' => $chunk_index,
            'save_name' => $saveName,
            'save_path' => $savePath,
            'size' => (int) filesize($savePath),
            'md5' => $actualMd5,
        ];
    }

    /**
     * @desc: 查询分片上传进度，用于断点续传
     */
    public function chunkProgress(string $file_id, int $total_chunks): array
    {
        $this->assertValidFileId($file_id);
        if ($total_chunks <= 0) {
            throw new StorageException('分片总数不合法：' . $total_chunks);
        }
        $uploaded = [];
        $missing = [];
        for ($i = 0; $i < $total_chunks; $i++) {
            if (is_file($this->chunkPartPath($file_id, $i))) {
                $uploaded[] = $i;
            } else {
                $missing[] = $i;
            }
        }

        return [
            'file_id' => $file_id,
            'total' => $total_chunks,
            'uploaded' => $uploaded,
            'missing' => $missing,
            'finished' => [] === $missing,
        ];
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
        $dir = $this->chunkDir($file_id);
        $lockPath = $dir . '.lock';
        // 先检查再 fopen('x')，避免已知存在时产生被 PHPUnit 捕获的 warning；'x' 模式仍兜底并发竞争
        if (is_file($lockPath)) {
            throw new StorageException('文件正在合并中，请勿重复操作');
        }
        $lock = @fopen($lockPath, 'x');
        if (false === $lock) {
            throw new StorageException('文件正在合并中，请勿重复操作');
        }

        try {
            $tmpPath = $dir . '.merging.part';
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
            $extension = substr($file_name, strripos($file_name, '.') + 1);
            $basePath = $this->config['root'] . $this->config['dirname'] . DIRECTORY_SEPARATOR;
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
            $this->removeDir($dir);
            $baseUrl =
                $this->config['domain']
                . $this->config['uri']
                . str_replace(DIRECTORY_SEPARATOR, '/', $this->config['dirname'])
                . DIRECTORY_SEPARATOR;
            $mimeType = function_exists('mime_content_type') ? mime_content_type($savePath) : false;

            return [
                'origin_name' => $file_name,
                'save_name' => $saveFilename,
                'save_path' => $savePath,
                'url' => $baseUrl . $saveFilename,
                'unique_id' => $uniqueId,
                'size' => $size,
                'mime_type' => $mimeType ?: 'application/octet-stream',
                'extension' => $extension,
            ];
        } finally {
            fclose($lock);
            @unlink($lockPath);
        }
    }

    /**
     * @desc: 加载配置文件，追加分片上传配置解析（disk 段 → storage 顶层 → 类常量，三级兜底）
     */
    protected function loadConfig(array $config)
    {
        parent::loadConfig($config);
        $defaultConfig = (array) config('plugin.tinywan.storage.app.storage');
        $this->chunkSize = (int) (
            $this->config['chunk_size'] ?? $defaultConfig['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE
        );
        $this->chunkPath = (string) (
            $this->config['chunk_path'] ?? $defaultConfig['local']['chunk_path']
            ?? rtrim((string) $this->config['root'], '/\\') . '/.chunks'
        );
    }

    /**
     * @desc: 校验 file_id 合法性，防止路径穿越
     */
    protected function assertValidFileId(string $file_id): void
    {
        if (!preg_match(self::FILE_ID_PATTERN, $file_id)) {
            throw new StorageException(
                'file_id 不合法：仅允许字母、数字、下划线、中划线，以字母或数字开头，长度不超过64',
            );
        }
    }

    /**
     * @desc: 分片临时目录，不按日期嵌套（断点续传可能跨天）
     */
    protected function chunkDir(string $file_id): string
    {
        return $this->chunkPath . DIRECTORY_SEPARATOR . $file_id;
    }

    /**
     * @desc: 分片文件路径，定长补零保证字典序即数字序
     */
    protected function chunkPartPath(string $file_id, int $chunk_index): string
    {
        return $this->chunkDir($file_id) . DIRECTORY_SEPARATOR . sprintf('%08d', $chunk_index) . '.part';
    }

    /**
     * @desc: 递归删除目录
     */
    protected function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (false === $items) {
            return;
        }
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * @desc: createDir 描述
     */
    protected function createDir(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        $parent = dirname($path);
        if (!is_dir($parent)) {
            if (!$this->createDir($parent)) {
                return false;
            }
        }

        return mkdir($path);
    }
}
