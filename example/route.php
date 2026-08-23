<?php
/**
 * 分片上传示例路由，将以下内容追加到 webman 项目的 config/route.php
 */
use Webman\Route;

Route::post('/upload/chunk', [app\controller\UploadController::class, 'chunk']);
Route::get('/upload/progress', [app\controller\UploadController::class, 'progress']);
Route::post('/upload/merge', [app\controller\UploadController::class, 'merge']);
