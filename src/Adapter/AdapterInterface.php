<?php
/**
 * @desc AdapterInterface 适配器接口
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/9 10:07
 */
declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

use Webman\Http\UploadFile;

interface AdapterInterface
{
    /**
     * @desc: 上传文件
     *
     * @return mixed
     */
    public function uploadFile(array $options);

    /**
     * @desc: 上传服务端文件
     *
     * @return mixed
     */
    public function uploadServerFile(string $file_path);

    /**
     * @desc: Base64上传文件
     *
     * @return mixed
     */
    public function uploadBase64(string $base64, string $extension = 'png');

    /**
     * @desc: 上传文件分片
     *
     * @return mixed
     */
    public function uploadChunk(
        UploadFile $file,
        string $file_id,
        int $chunk_index,
        string $chunk_md5,
        array $options = [],
    );

    /**
     * @desc: 查询分片上传进度（断点续传）
     *
     * @return mixed
     */
    public function chunkProgress(string $file_id, int $total_chunks);

    /**
     * @desc: 合并文件分片
     *
     * @return mixed
     */
    public function mergeChunk(string $file_id, int $total_chunks, string $file_name, array $options = []);
}
