<?php
declare(strict_types=1);

use Tinywan\Storage\Adapter\LocalAdapter;
use Webman\Http\UploadFile;

/*
 * 本地适配器测试专用子类：
 * _is_file_upload = false 时构造函数不会从 request() 读取文件，
 * 这里提供注入口直接塞入待上传文件。
 */
function makeLocalAdapter(string $root)
{
    return new class([
        'adapter' => LocalAdapter::class,
        'root' => $root,
        'dirname' => 'unit-test',
        'domain' => 'http://127.0.0.1:8787',
        'uri' => '/storage',
        'algo' => 'sha1',
        '_is_file_upload' => false,
    ]) extends LocalAdapter {
        /**
         * @param UploadFile[] $files
         */
        public function withFiles(array $files): self
        {
            $this->files = $files;

            return $this;
        }
    };
}

function makeTempFile(string $content): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'storage_test_');
    file_put_contents($path, $content);

    return $path;
}

test('本地上传文件返回标准信息且文件落盘', function () {
    $root = sys_get_temp_dir() . '/storage_test_' . uniqid();
    $tmpFile = makeTempFile('hello webman storage');
    // 上传过程会移动源文件，需在上传前计算哈希
    $expectedHash = sha1_file($tmpFile);

    $uploadFile = new UploadFile($tmpFile, 'demo.txt', 'text/plain', UPLOAD_ERR_OK);
    $result = makeLocalAdapter($root)->withFiles([$uploadFile])->uploadFile();

    expect($result)->toHaveCount(1);

    $file = $result[0];
    expect($file)->toHaveKeys([
        'key',
        'origin_name',
        'save_name',
        'save_path',
        'url',
        'unique_id',
        'size',
        'mime_type',
        'extension',
    ]);
    expect($file['origin_name'])->toBe('demo.txt');
    expect($file['extension'])->toBe('txt');
    expect($file['size'])->toBe(strlen('hello webman storage'));
    expect($file['unique_id'])->toBe($expectedHash);
    // url = domain + uri + dirname + 分隔符（现有拼接行为）
    expect($file['url'])->toBe('http://127.0.0.1:8787/storageunit-test/' . $file['save_name']);
    expect($file['save_path'])->toBeFile();
});

test('未实现的能力返回失败并记录错误信息', function () {
    $root = sys_get_temp_dir() . '/storage_test_' . uniqid();
    $adapter = makeLocalAdapter($root);

    expect($adapter->uploadBase64('data:image/png;base64,xxx'))
        ->toBeFalse()
        ->and($adapter->getMessage())
        ->toBe('暂不支持')
        ->and($adapter->uploadServerFile(__FILE__))
        ->toBeFalse();
});
