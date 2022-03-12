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
use Webman\Http\UploadFile;

abstract class AdapterAbstract implements AdapterInterface
{
    /**
     * 文件存储对象
     */
    protected $files;

    /**
     * 被允许的文件类型列表.
     */
    protected array $includes;

    /**
     * 不被允许的文件类型列表.
     */
    protected array $excludes;

    /**
     * 单个文件的最大字节数.
     */
    protected int $singleLimit;

    /**
     * 多个文件的最大数量.
     */
    protected int $totalLimit;

    /**
     * 文件上传的最大数量.
     */
    protected int $nums;

    /**
     * AdapterAbstract constructor.
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function __construct(array $config = [])
    {
        $this->files = request()->file();
        $this->includes = [];
        $this->excludes = [];
        $this->singleLimit = 0;
        $this->totalLimit = 0;
        $this->nums = 0;
        $this->loadConfig($config);
        $this->verify();
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
        return $file->getSize();
    }

    /**
     * @desc: 允许上传文件
     *
     * @author Tinywan(ShaoBo Wan)
     *
     * @throws UploadFileException
     */
    protected function allowedFile(): bool
    {
        if ((!empty($this->includes) && !empty($this->excludes)) || !empty($this->includes)) {
            foreach ($this->files as $file) {
                $fileName = $file->getUploadName();
                if (!strpos($fileName, '.') || !in_array(substr($fileName, strripos($fileName, '.') + 1), $this->includes)) {
                    throw new StorageException($file->getUploadName().'，文件扩展名不合法');
                }
            }
        } elseif (!empty($this->excludes) && empty($this->includes)) {
            foreach ($this->files as $file) {
                $fileName = $file->getUploadName();
                if (!strpos($fileName, '.') || in_array(substr($fileName, strripos($fileName, '.') + 1), $this->excludes)) {
                    throw new StorageException($file->getUploadName().'，文件扩展名不合法');
                }
            }
        }

        return true;
    }

    /**
     * @desc: 允许上传文件大小
     *
     * @author Tinywan(ShaoBo Wan)
     *
     * @throws UploadFileException
     */
    protected function allowedFileSize()
    {
        $fileCount = count($this->files);
        if ($fileCount > $this->nums) {
            throw new StorageException('文件数量过多，超出系统文件数量限制');
        }
        $totalSize = 0;
        foreach ($this->files as $k => $file) {
            $fileSize = $this->getSize($this->files[$k]);
            if ($fileSize > $this->singleLimit) {
                throw new StorageException($file->getUploadName().'，单文件大小已超出系统限制：'.$this->singleLimit);
            }
            $totalSize += $fileSize;
        }
        if ($totalSize > $this->totalLimit) {
            throw new StorageException('总文件大小已超出系统最大限制：'.$this->totalLimit);
        }
    }
}
