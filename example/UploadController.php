<?php
/**
 * 大文件分片上传示例控制器（webman 1.x / 2.x 通用）
 *
 * 使用方式：将本文件复制到 webman 项目的 app/controller/ 目录，
 * 并按 example/route.php 注册路由。
 */
declare(strict_types=1);

namespace app\controller;

use Tinywan\Storage\Storage;
use Webman\Http\Request;

class UploadController
{
    /**
     * 上传单个分片
     * POST /upload/chunk  form-data: file（分片文件）、file_id、chunk_index、chunk_md5
     */
    public function chunk(Request $request)
    {
        $file = $request->file('file');
        if (!$file) {
            return json(['code' => 400, 'msg' => '未找到分片文件'], 400);
        }

        try {
            $data = Storage::disk(Storage::MODE_LOCAL, false)->uploadChunk(
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
     * GET /upload/progress?file_id=xxx&total_chunks=3
     */
    public function progress(Request $request)
    {
        try {
            $data = Storage::disk(Storage::MODE_LOCAL, false)->chunkProgress(
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
     * POST /upload/merge  form-data: file_id、total_chunks、file_name（完整文件名，如 video.mp4）
     */
    public function merge(Request $request)
    {
        try {
            $data = Storage::disk(Storage::MODE_LOCAL, false)->mergeChunk(
                (string) $request->post('file_id', ''),
                (int) $request->post('total_chunks', 0),
                (string) $request->post('file_name', ''),
            );

            return json(['code' => 0, 'msg' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }
}
