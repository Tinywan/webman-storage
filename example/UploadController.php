<?php
/**
 * 大文件分片上传示例控制器（webman 1.x / 2.x 通用，本地与云端通用）
 *
 * 使用方式：将本文件复制到 webman 项目的 app/controller/ 目录，
 * 并按 example/route.php 注册路由。
 *
 * 所有接口通过 disk 参数选择存储（默认 local）：local / oss / cos / qiniu / s3，
 * 需在 config/plugin/tinywan/storage/app.php 配置对应 disk 后使用。
 */
declare(strict_types=1);

namespace app\controller;

use Tinywan\Storage\Storage;
use Webman\Http\Request;

class UploadController
{
    /**
     * 允许的 disk 白名单
     *
     * @var string[]
     */
    private const DISKS = [
        Storage::MODE_LOCAL,
        Storage::MODE_OSS,
        Storage::MODE_COS,
        Storage::MODE_QINIU,
        Storage::MODE_S3,
    ];

    /**
     * 上传单个分片
     * POST /upload/chunk  form-data: file（分片文件）、file_id、chunk_index、chunk_md5、disk（可选）
     */
    public function chunk(Request $request)
    {
        $file = $request->file('file');
        if (!$file) {
            return json(['code' => 400, 'msg' => '未找到分片文件'], 400);
        }

        try {
            $data = $this->disk($request->post('disk', Storage::MODE_LOCAL))->uploadChunk(
                $file,
                (string) $request->post('file_id', ''),
                (int) $request->post('chunk_index', 0),
                (string) $request->post('chunk_md5', ''),
            );

            return json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * 断点续传进度查询
     * GET /upload/progress?file_id=xxx&total_chunks=3&disk=local
     */
    public function progress(Request $request)
    {
        try {
            $data = $this->disk($request->get('disk', Storage::MODE_LOCAL))->chunkProgress(
                (string) $request->get('file_id', ''),
                (int) $request->get('total_chunks', 0),
            );

            return json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * 合并全部分片
     * POST /upload/merge  form-data: file_id、total_chunks、file_name（完整文件名，如 video.mp4）、disk（可选）
     *
     * 云端合并建议传 file_hash（整文件散列）作为最终对象名；同时可传 mime_type
     * （云端无法探测 MIME，否则默认 application/octet-stream）
     */
    public function merge(Request $request)
    {
        try {
            $options = [];
            $fileHash = (string) $request->post('file_hash', '');
            if ('' !== $fileHash) {
                $options['file_hash'] = $fileHash;
            }
            $mimeType = (string) $request->post('mime_type', '');
            if ('' !== $mimeType) {
                $options['mime_type'] = $mimeType;
            }

            $data = $this->disk($request->post('disk', Storage::MODE_LOCAL))->mergeChunk(
                (string) $request->post('file_id', ''),
                (int) $request->post('total_chunks', 0),
                (string) $request->post('file_name', ''),
                $options,
            );

            return json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * 按参数解析存储 disk，非法值抛异常
     */
    private function disk(string $disk)
    {
        if (!in_array($disk, self::DISKS, true)) {
            throw new \InvalidArgumentException('不支持的 disk：' . $disk);
        }

        return Storage::disk($disk, false);
    }
}
