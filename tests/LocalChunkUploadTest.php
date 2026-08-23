<?php
declare(strict_types=1);

use Tinywan\Storage\Adapter\CosAdapter;
use Tinywan\Storage\Adapter\LocalAdapter;
use Tinywan\Storage\Adapter\OssAdapter;
use Tinywan\Storage\Adapter\QiniuAdapter;
use Tinywan\Storage\Adapter\S3Adapter;
use Tinywan\Storage\Exception\StorageException;
use Webman\Http\UploadFile;

function makeChunkAdapter(string $root, array $overrides = []): LocalAdapter
{
    return new class(array_merge([
        'adapter' => LocalAdapter::class,
        'root' => $root,
        'dirname' => 'unit-test',
        'domain' => 'http://127.0.0.1:8787',
        'uri' => '/storage',
        'algo' => 'sha1',
        'chunk_size' => 1024,
        'chunk_path' => $root . '.chunks',
        '_is_file_upload' => false,
    ], $overrides)) extends LocalAdapter {};
}

function makeChunkFile(string $content): UploadFile
{
    $path = tempnam(sys_get_temp_dir(), 'storage_chunk_');
    file_put_contents($path, $content);

    return new UploadFile($path, 'chunk.part', 'application/octet-stream', UPLOAD_ERR_OK);
}

function makeChunkRoot(): string
{
    return sys_get_temp_dir() . '/storage_chunk_' . uniqid() . '/';
}

test('分片上传成功落盘并返回标准信息', function () {
    $content = str_repeat('A', 512);
    $result = makeChunkAdapter(makeChunkRoot())->uploadChunk(makeChunkFile($content), 'demo-file', 0, md5($content));

    expect($result)
        ->toHaveKeys(['file_id', 'chunk_index', 'save_name', 'save_path', 'size', 'md5'])
        ->and($result['file_id'])
        ->toBe('demo-file')
        ->and($result['chunk_index'])
        ->toBe(0)
        ->and($result['save_name'])
        ->toBe('00000000.part')
        ->and($result['size'])
        ->toBe(512)
        ->and($result['md5'])
        ->toBe(md5($content))
        ->and($result['save_path'])
        ->toBeFile()
        ->and(file_get_contents($result['save_path']))
        ->toBe($content);
});

test('分片 md5 校验失败时抛异常且无残留文件', function () {
    $root = makeChunkRoot();
    $adapter = makeChunkAdapter($root);

    expect(fn() => $adapter->uploadChunk(makeChunkFile('hello'), 'demo-file', 0, 'invalid-md5'))
        ->toThrow(StorageException::class, 'md5');
    expect(is_dir($root . '.chunks/demo-file'))->toBeFalse();
});

test('file_id 非法时抛异常防止路径穿越', function (string $fileId) {
    $adapter = makeChunkAdapter(makeChunkRoot());

    expect(fn() => $adapter->chunkProgress($fileId, 4))->toThrow(StorageException::class, 'file_id');
})->with(['../etc', '', str_repeat('a', 65), '-leading-dash', '中文id']);

test('分片大小超出限制时抛异常', function () {
    $adapter = makeChunkAdapter(makeChunkRoot(), ['chunk_size' => 10]);
    $content = str_repeat('B', 32);

    expect(fn() => $adapter->uploadChunk(makeChunkFile($content), 'demo-file', 0, md5($content)))
        ->toThrow(StorageException::class, '分片大小超出限制');
});

test('断点续传查询返回已传与缺失分片', function () {
    $adapter = makeChunkAdapter(makeChunkRoot());
    $adapter->uploadChunk(makeChunkFile('aaa'), 'demo-file', 0, md5('aaa'));
    $adapter->uploadChunk(makeChunkFile('ccc'), 'demo-file', 2, md5('ccc'));

    $progress = $adapter->chunkProgress('demo-file', 4);

    expect($progress['total'])
        ->toBe(4)
        ->and($progress['uploaded'])
        ->toBe([0, 2])
        ->and($progress['missing'])
        ->toBe([1, 3])
        ->and($progress['finished'])
        ->toBeFalse();
});

test('未知 file_id 查询进度返回全量缺失', function () {
    $progress = makeChunkAdapter(makeChunkRoot())->chunkProgress('no-such-file', 3);

    expect($progress['uploaded'])
        ->toBe([])
        ->and($progress['missing'])
        ->toBe([0, 1, 2])
        ->and($progress['finished'])
        ->toBeFalse();
});

test('合并分片后内容与源文件逐字节一致', function () {
    $root = makeChunkRoot();
    $adapter = makeChunkAdapter($root, ['chunk_size' => 512]);
    $origin = str_repeat('webman-storage-', 100);
    $chunks = str_split($origin, 512);
    foreach ($chunks as $index => $chunk) {
        $adapter->uploadChunk(makeChunkFile($chunk), 'demo-file', $index, md5($chunk));
    }

    $result = $adapter->mergeChunk('demo-file', count($chunks), 'video.mp4');

    expect($result)
        ->toHaveKeys(['origin_name', 'save_name', 'save_path', 'url', 'unique_id', 'size', 'mime_type', 'extension'])
        ->and($result['origin_name'])
        ->toBe('video.mp4')
        ->and($result['extension'])
        ->toBe('mp4')
        ->and($result['size'])
        ->toBe(strlen($origin))
        ->and($result['unique_id'])
        ->toBe(sha1($origin))
        ->and($result['url'])
        ->toStartWith('http://127.0.0.1:8787/storage')
        ->and(file_get_contents($result['save_path']))
        ->toBe($origin)
        ->and(is_dir($root . '.chunks/demo-file'))
        ->toBeFalse();
});

test('缺少分片时合并失败并提示缺失序号', function () {
    $adapter = makeChunkAdapter(makeChunkRoot());
    $adapter->uploadChunk(makeChunkFile('aaa'), 'demo-file', 0, md5('aaa'));

    expect(fn() => $adapter->mergeChunk('demo-file', 3, 'demo.txt'))->toThrow(StorageException::class, '缺少分片：1,2');
});

test('合并时校验文件后缀白名单与黑名单', function () {
    $includeAdapter = makeChunkAdapter(makeChunkRoot(), ['include' => ['png']]);
    expect(fn() => $includeAdapter->mergeChunk('demo-file', 1, 'demo.txt'))
        ->toThrow(StorageException::class, '文件扩展名不合法');

    $excludeAdapter = makeChunkAdapter(makeChunkRoot(), ['exclude' => ['php']]);
    expect(fn() => $excludeAdapter->mergeChunk('demo-file', 1, 'shell.php'))
        ->toThrow(StorageException::class, '文件扩展名不合法');

    $adapter = makeChunkAdapter(makeChunkRoot());
    $adapter->uploadChunk(makeChunkFile('zip'), 'demo-file', 0, md5('zip'));
    expect($adapter->mergeChunk('demo-file', 1, 'any.zip')['extension'])->toBe('zip');
});

test('同一分片序号重传覆盖旧分片', function () {
    $adapter = makeChunkAdapter(makeChunkRoot());
    $adapter->uploadChunk(makeChunkFile('AAA'), 'demo-file', 0, md5('AAA'));
    $adapter->uploadChunk(makeChunkFile('BBB'), 'demo-file', 0, md5('BBB'));

    $result = $adapter->mergeChunk('demo-file', 1, 'demo.txt');

    expect(file_get_contents($result['save_path']))->toBe('BBB');
});

test('合并锁存在时抛异常', function () {
    $root = makeChunkRoot();
    $adapter = makeChunkAdapter($root);
    $adapter->uploadChunk(makeChunkFile('AAA'), 'demo-file', 0, md5('AAA'));
    $lockPath = $root . '.chunks/demo-file.lock';
    file_put_contents($lockPath, 'lock');

    expect(fn() => $adapter->mergeChunk('demo-file', 1, 'demo.txt'))->toThrow(StorageException::class, '正在合并中');
    @unlink($lockPath);
});

test('云端适配器暂不支持分片上传', function (string $adapterClass) {
    $adapter = new $adapterClass(['_is_file_upload' => false, 'dirname' => 'storage']);

    expect($adapter->uploadChunk(makeChunkFile('x'), 'demo-file', 0, md5('x')))
        ->toBeFalse()
        ->and($adapter->chunkProgress('demo-file', 1))
        ->toBeFalse()
        ->and($adapter->mergeChunk('demo-file', 1, 'demo.txt'))
        ->toBeFalse()
        ->and($adapter->getMessage())
        ->toBe('暂不支持');
})->with([OssAdapter::class, CosAdapter::class, QiniuAdapter::class, S3Adapter::class]);
