<?php
/**
 * @desc AdapterAbstract 抽象适配器
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 19:54
 */
declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

use Tinywan\Storage\Exception\StorageException;
use Tinywan\Storage\Traits\ErrorMsg;
use Webman\Http\UploadFile;

abstract class AdapterAbstract implements AdapterInterface
{
    use ErrorMsg;

    /**
     * @var bool
     */
    public $_isFileUpload;

    /**
     * @var string
     */
    public $dirSeparator = DIRECTORY_SEPARATOR;

    /**
     * 文件存储对象.
     *
     * @var UploadFile[]
     */
    protected $files;

    /**
     * 被允许的文件类型列表.
     */
    protected $includes;

    /**
     * 不被允许的文件类型列表.
     */
    protected $excludes;

    /**
     * 单个文件的最大字节数.
     */
    protected $singleLimit;

    /**
     * 多个文件的最大数量.
     */
    protected $totalLimit;

    /**
     * 文件上传的最大数量.
     */
    protected $nums;

    /**
     * 当前存储配置.
     *
     * @var array
     */
    protected $config;

    /**
     * 命名规则 eg：md5：对文件使用md5_file散列生成，sha1：对文件使用sha1_file散列生成.
     *
     * @var string
     */
    protected $algo = 'md5';

    /**
     * 默认分片大小：5M.
     */
    public const DEFAULT_CHUNK_SIZE = 5242880;

    /**
     * file_id 白名单规则：字母或数字开头，仅允许字母、数字、下划线、中划线，长度不超过64，天然免疫路径穿越.
     */
    public const FILE_ID_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/';

    /**
     * 单个分片的最大字节数.
     *
     * @var int
     */
    protected $chunkSize;

    /**
     * 分片临时存储目录（本地暂存与云端会话状态共用）.
     *
     * @var string
     */
    protected $chunkPath;

    /**
     * AdapterAbstract constructor.
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function __construct(array $config = [])
    {
        $this->loadConfig($config);
        $this->dirSeparator = \DIRECTORY_SEPARATOR === '\\' ? '/' : DIRECTORY_SEPARATOR;
        $this->_isFileUpload = (bool) ($config['_is_file_upload'] ?? true);
        if ($this->_isFileUpload) {
            $this->files = (array) request()->file();
            $this->includes = [];
            $this->excludes = [];
            $this->singleLimit = 0;
            $this->totalLimit = 0;
            $this->nums = 0;
            $this->verify();
        }
    }

    /**
     * @return array|bool
     */
    public function uploadBase64(string $base64, string $extension = 'png')
    {
        return $this->setError(false, '暂不支持');
    }

    /**
     * @return array|bool
     */
    public function uploadServerFile(string $file_path)
    {
        return $this->setError(false, '暂不支持');
    }

    /**
     * @desc: 加载配置文件
     *
     * @author Tinywan(ShaoBo Wan)
     */
    protected function loadConfig(array $config)
    {
        $defaultConfig = config('plugin.tinywan.storage.app.storage');
        $this->includes = $config['include'] ?? $defaultConfig['include'];
        $this->excludes = $config['exclude'] ?? $defaultConfig['exclude'];
        $this->singleLimit = $config['single_limit'] ?? $defaultConfig['single_limit'];
        $this->totalLimit = $config['total_limit'] ?? $defaultConfig['total_limit'];
        $this->nums = $config['nums'] ?? $defaultConfig['nums'];
        $this->algo = (string) ($config['algo'] ?? $this->algo);
        $this->config = $config;
        $this->config['includes'] = $config['include'] ?? $defaultConfig['include'];
        $this->config['excludes'] = $config['exclude'] ?? $defaultConfig['exclude'];
        $this->config['single_limit'] = $config['single_limit'] ?? $defaultConfig['single_limit'];
        $this->config['total_limit'] = $config['total_limit'] ?? $defaultConfig['total_limit'];
        $this->config['total_limit'] = $config['total_limit'] ?? $defaultConfig['total_limit'];
        $this->config['nums'] = $config['nums'] ?? $defaultConfig['nums'];
        $this->config['algo'] = $config['algo'] ?? $this->algo;
        if (is_callable($this->config['dirname'])) {
            $this->config['dirname'] = (string) $this->config['dirname']() ?: $this->config['dirname'];
        }
        $this->chunkSize = (int) (
            $this->config['chunk_size'] ?? $defaultConfig['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE
        );
        $this->chunkPath = (string) (
            $this->config['chunk_path'] ?? $defaultConfig['chunk_path'] ?? $this->defaultChunkPath()
        );
    }

    /**
     * @desc: chunk_path 兜底：有 root 的适配器（本地）落在 {root}/.chunks，云端适配器落在 runtime/storage/.chunks
     */
    protected function defaultChunkPath(): string
    {
        if (!empty($this->config['root'])) {
            return rtrim((string) $this->config['root'], '/\\') . '/.chunks';
        }

        return rtrim(runtime_path(), '/\\') . '/storage/.chunks';
    }

    /**
     * @desc: 文件验证
     *
     * @author Tinywan(ShaoBo Wan)
     */
    protected function verify()
    {
        if (!$this->files) {
            throw new StorageException('未找到符合条件的文件资源');
        }
        foreach ($this->files as $file) {
            if (!$file->isValid()) {
                throw new StorageException('未选择文件或者无效的文件');
            }
        }
        $this->allowedFile();
        $this->allowedFileSize();
    }

    /**
     * @desc: 获取文件大小
     *
     * @author Tinywan(ShaoBo Wan)
     */
    protected function getSize(UploadFile $file): int
    {
        return (int) $file->getSize();
    }

    /**
     * @desc: 校验文件名后缀是否被允许（includes 白名单优先，其次 excludes 黑名单，皆空放行）
     */
    protected function allowedExtension(string $file_name): bool
    {
        $pos = strripos($file_name, '.');
        $extension = false === $pos ? '' : substr($file_name, $pos + 1);
        if (!empty($this->config['includes'])) {
            return '' !== $extension && in_array($extension, $this->config['includes'], true);
        }
        if (!empty($this->config['excludes'])) {
            return '' !== $extension && !in_array($extension, $this->config['excludes'], true);
        }

        return true;
    }

    /**
     * @desc: 允许上传文件
     *
     * @author Tinywan(ShaoBo Wan)
     */
    protected function allowedFile(): bool
    {
        if (!empty($this->includes) && !empty($this->config['excludes']) || !empty($this->config['includes'])) {
            foreach ($this->files as $file) {
                $fileName = $file->getUploadName();
                if (
                    !strpos($fileName, '.')
                    || !in_array(substr($fileName, strripos($fileName, '.') + 1), $this->config['includes'], true)
                ) {
                    throw new StorageException($file->getUploadName() . '，文件扩展名不合法');
                }
            }
        } elseif (!empty($this->config['excludes']) && empty($this->config['includes'])) {
            foreach ($this->files as $file) {
                $fileName = $file->getUploadName();
                if (
                    !strpos($fileName, '.')
                    || in_array(substr($fileName, strripos($fileName, '.') + 1), $this->config['excludes'], true)
                ) {
                    throw new StorageException($file->getUploadName() . '，文件扩展名不合法');
                }
            }
        }

        return true;
    }

    /**
     * @desc: 允许上传文件大小
     *
     * @author Tinywan(ShaoBo Wan)
     */
    protected function allowedFileSize()
    {
        $fileCount = count($this->files);
        if ($fileCount > $this->config['nums']) {
            throw new StorageException('文件数量过多，超出系统文件数量限制');
        }
        $totalSize = 0;
        foreach ($this->files as $k => $file) {
            $fileSize = $this->getSize($this->files[$k]);
            if ($fileSize > $this->config['single_limit']) {
                throw new StorageException(
                    $file->getUploadName() . '，单文件大小已超出系统限制：' . $this->config['single_limit'],
                );
            }
            $totalSize += $fileSize;
        }
        if ($totalSize > $this->config['total_limit']) {
            throw new StorageException('总文件大小已超出系统最大限制：' . $this->config['total_limit']);
        }
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
     * @desc: 分片上传前置校验（file_id、序号、大小、md5），返回实际 md5
     */
    protected function assertChunkUploadable(
        UploadFile $file,
        string $file_id,
        int $chunk_index,
        string $chunk_md5,
    ): string {
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

        return $actualMd5;
    }

    /**
     * @desc: 分片临时目录，不按日期嵌套（断点续传可能跨天）
     */
    protected function chunkDir(string $file_id): string
    {
        return $this->chunkPath . DIRECTORY_SEPARATOR . $file_id;
    }

    /**
     * @desc: 分片文件名，定长补零保证字典序即数字序
     */
    protected function chunkPartName(int $chunk_index): string
    {
        return sprintf('%08d.part', $chunk_index);
    }

    /**
     * @desc: 分片文件路径
     */
    protected function chunkPartPath(string $file_id, int $chunk_index): string
    {
        return $this->chunkDir($file_id) . DIRECTORY_SEPARATOR . $this->chunkPartName($chunk_index);
    }

    /**
     * @desc: 分片落盘（本地暂存），同一 chunk_index 重复上传将覆盖（断点续传语义）
     */
    protected function saveStagedChunk(UploadFile $file, string $file_id, int $chunk_index, string $actualMd5): array
    {
        $dir = $this->chunkDir($file_id);
        if (!$this->createDir($dir)) {
            throw new StorageException('分片目录创建失败，请核查是否有对应权限。');
        }
        $saveName = $this->chunkPartName($chunk_index);
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
     * @desc: 基于本地暂存文件的分片进度查询
     */
    protected function stagedChunkProgress(string $file_id, int $total_chunks): array
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
     * @desc: 获取合并锁，防止并发合并。返回 [句柄, 锁文件路径]
     *
     * @return array{0: resource, 1: string}
     */
    protected function acquireMergeLock(string $file_id): array
    {
        $lockPath = $this->chunkDir($file_id) . '.lock';
        // 先检查再 fopen('x')，避免已知存在时产生被 PHPUnit 捕获的 warning；'x' 模式仍兜底并发竞争
        if (is_file($lockPath)) {
            throw new StorageException('文件正在合并中，请勿重复操作');
        }
        $lock = @fopen($lockPath, 'x');
        if (false === $lock) {
            throw new StorageException('文件正在合并中，请勿重复操作');
        }

        return [$lock, $lockPath];
    }

    /**
     * @desc: 释放合并锁
     *
     * @param array{0: resource, 1: string} $lock
     */
    protected function releaseMergeLock(array $lock): void
    {
        fclose($lock[0]);
        @unlink($lock[1]);
    }

    /**
     * @desc: 云端分片会话文件路径（记录 uploadId 与各分片 ETag）
     */
    protected function sessionPath(string $file_id): string
    {
        return $this->chunkDir($file_id) . DIRECTORY_SEPARATOR . 'session.json';
    }

    /**
     * @desc: 读取云端分片会话，不存在或损坏时返回空数组
     */
    protected function loadSession(string $file_id): array
    {
        $path = $this->sessionPath($file_id);
        if (!is_file($path)) {
            return [];
        }
        $session = json_decode((string) file_get_contents($path), true);

        return is_array($session) ? $session : [];
    }

    /**
     * @desc: 记录单个分片的 ETag 信息到会话，返回更新后的会话
     */
    protected function withSessionPart(array $session, int $chunk_index, array $part): array
    {
        $parts = isset($session['parts']) && is_array($session['parts']) ? $session['parts'] : [];
        $parts[$chunk_index] = $part;
        $session['parts'] = $parts;

        return $session;
    }

    /**
     * @desc: 持久化云端分片会话
     */
    protected function saveSession(string $file_id, array $session): void
    {
        $dir = $this->chunkDir($file_id);
        if (!$this->createDir($dir)) {
            throw new StorageException('分片目录创建失败，请核查是否有对应权限。');
        }
        file_put_contents($this->sessionPath($file_id), (string) json_encode($session));
    }

    /**
     * @desc: 基于云端会话的分片进度查询
     */
    protected function sessionChunkProgress(string $file_id, int $total_chunks): array
    {
        $this->assertValidFileId($file_id);
        if ($total_chunks <= 0) {
            throw new StorageException('分片总数不合法：' . $total_chunks);
        }
        $parts = $this->loadSession($file_id)['parts'] ?? [];
        $uploaded = [];
        $missing = [];
        for ($i = 0; $i < $total_chunks; $i++) {
            if (isset($parts[$i])) {
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
     * @desc: 云端会话中缺失的分片序号列表
     *
     * @param array<int|string, mixed> $parts
     *
     * @return int[]
     */
    protected function missingSessionParts(array $parts, int $total_chunks): array
    {
        $missing = [];
        for ($i = 0; $i < $total_chunks; $i++) {
            if (!isset($parts[$i])) {
                $missing[] = $i;
            }
        }

        return $missing;
    }

    /**
     * @desc: 提取文件名扩展名
     */
    protected function extensionOf(string $file_name): string
    {
        return substr($file_name, strripos($file_name, '.') + 1);
    }

    /**
     * @desc: 云端合并最终命名：options.file_hash 存在时用散列命名，否则用 file_id 命名。
     *        返回 [最终文件名, 扩展名, unique_id]
     *
     * @return array{0: string, 1: string, 2: string}
     */
    protected function chunkFinalName(string $file_id, string $file_name, array $options): array
    {
        $extension = $this->extensionOf($file_name);
        $fileHash = (string) ($options['file_hash'] ?? '');
        $uniqueId = '' !== $fileHash ? $fileHash : $file_id;

        return [$uniqueId . '.' . $extension, $extension, $uniqueId];
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
     * @desc: 递归创建目录
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
