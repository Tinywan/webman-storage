<?php
declare(strict_types=1);

use OSS\OssClient;
use Tinywan\Storage\Adapter\CosAdapter;
use Tinywan\Storage\Adapter\OssAdapter;
use Tinywan\Storage\Adapter\QiniuAdapter;
use Tinywan\Storage\Adapter\S3Adapter;
use Tinywan\Storage\Exception\StorageException;
use Webman\Http\UploadFile;

/*
 * 云端分片测试替身：全部以 fake 客户端替换 getInstance()，
 * 记录调用序列、返回预设响应，不产生任何真实网络请求。
 */

class FakeOssClient extends OssClient
{
    /** @var array<int, array{0: string, 1: array}> */
    public $calls = [];

    public function initiateMultipartUpload($bucket, $object, $options = null)
    {
        $this->calls[] = ['initiateMultipartUpload', [$bucket, $object]];

        return 'fake-upload-id';
    }

    public function uploadPart($bucket, $object, $uploadId, $options = null)
    {
        $this->calls[] = ['uploadPart', [$bucket, $object, $uploadId, $options]];

        return 'etag-' . $options[OssClient::OSS_PART_NUM];
    }

    public function completeMultipartUpload($bucket, $object, $uploadId, $listParts, $options = null)
    {
        $this->calls[] = ['completeMultipartUpload', [$bucket, $object, $uploadId, $listParts]];

        return null;
    }

    public function copyObject($fromBucket, $fromObject, $toBucket, $toObject, $options = null)
    {
        $this->calls[] = ['copyObject', [$fromBucket, $fromObject, $toBucket, $toObject]];

        return null;
    }

    public function deleteObject($bucket, $object, $options = null)
    {
        $this->calls[] = ['deleteObject', [$bucket, $object]];

        return null;
    }

    /**
     * @return string[]
     */
    public function callNames(): array
    {
        $names = [];
        foreach ($this->calls as $call) {
            $names[] = (string) $call[0];
        }

        return $names;
    }
}

class FakeCosClient extends \Qcloud\Cos\Client
{
    /** @var array<int, array{0: string, 1: array}> */
    public $calls = [];

    /**
     * 测试替身：返回真实 Result 对象（实现 ArrayAccess），适配器按数组方式访问
     */
    public function __call($method, array $args)
    {
        $this->calls[] = [$method, $args];
        if ('createMultipartUpload' === $method) {
            return new \GuzzleHttp\Command\Result(['UploadId' => 'fake-upload-id']);
        }
        if ('uploadPart' === $method) {
            return new \GuzzleHttp\Command\Result(['ETag' => 'etag-' . $args[0]['PartNumber']]);
        }

        return new \GuzzleHttp\Command\Result([]);
    }

    /**
     * @return string[]
     */
    public function callNames(): array
    {
        $names = [];
        foreach ($this->calls as $call) {
            $names[] = (string) $call[0];
        }

        return $names;
    }
}

class FakeS3Client extends \Aws\S3\S3Client
{
    /** @var array<int, array{0: string, 1: array}> */
    public $calls = [];

    public function __construct(array $args = [])
    {
        // 测试替身：跳过父类构造（SDK 服务解析/凭证校验），所有命令由 __call 拦截
    }

    /**
     * 测试替身：返回真实 Result 对象（实现 ArrayAccess），适配器按数组方式访问
     */
    public function __call($name, array $args)
    {
        $this->calls[] = [$name, $args];
        if ('createMultipartUpload' === $name) {
            return new \Aws\Result(['UploadId' => 'fake-upload-id']);
        }
        if ('uploadPart' === $name) {
            return new \Aws\Result(['ETag' => 'etag-' . $args[0]['PartNumber']]);
        }

        return new \Aws\Result([]);
    }

    /**
     * @return string[]
     */
    public function callNames(): array
    {
        $names = [];
        foreach ($this->calls as $call) {
            $names[] = (string) $call[0];
        }

        return $names;
    }
}

function makeCloudChunkRoot(): string
{
    return sys_get_temp_dir() . '/storage_cloud_' . uniqid() . '/';
}

function makeCloudChunkFile(string $content): UploadFile
{
    $path = tempnam(sys_get_temp_dir(), 'storage_cloud_chunk_');
    file_put_contents($path, $content);

    return new UploadFile($path, 'chunk.part', 'application/octet-stream', UPLOAD_ERR_OK);
}

class OssChunkTestAdapter extends OssAdapter
{
    /** @var FakeOssClient|null */
    public $fakeClient;

    public function getInstance(): OssClient
    {
        if (is_null($this->fakeClient)) {
            $this->fakeClient = new FakeOssClient('fake-ak', 'fake-sk', 'oss-cn-hangzhou.aliyuncs.com');
        }

        return $this->fakeClient;
    }
}

class CosChunkTestAdapter extends CosAdapter
{
    /** @var FakeCosClient|null */
    public $fakeClient;

    public function getInstance(): \Qcloud\Cos\Client
    {
        if (is_null($this->fakeClient)) {
            $this->fakeClient = new FakeCosClient([
                'region' => 'ap-shanghai',
                'credentials' => ['secretId' => 'fake-ak', 'secretKey' => 'fake-sk'],
            ]);
        }

        return $this->fakeClient;
    }
}

class S3ChunkTestAdapter extends S3Adapter
{
    /** @var FakeS3Client|null */
    public $fakeClient;

    public function getInstance(): \Aws\S3\S3Client
    {
        if (is_null($this->fakeClient)) {
            $this->fakeClient = new FakeS3Client([
                'version' => 'latest',
                'region' => 'us-east-1',
                'credentials' => ['key' => 'fake-ak', 'secret' => 'fake-sk'],
            ]);
        }

        return $this->fakeClient;
    }
}

class QiniuChunkTestAdapter extends QiniuAdapter
{
    /** @var array<int, array{0: string, 1: string}> */
    public $uploads = [];

    protected function uploadMergedFile(string $object, string $file_path): void
    {
        $this->uploads[] = [$object, (string) file_get_contents($file_path)];
    }
}

function makeOssChunkAdapter(string $root, array $overrides = []): OssChunkTestAdapter
{
    return new OssChunkTestAdapter(array_merge([
        'adapter' => OssAdapter::class,
        'accessKeyId' => 'fake-ak',
        'accessKeySecret' => 'fake-sk',
        'bucket' => 'test-bucket',
        'dirname' => 'storage',
        'domain' => 'http://cdn.example.com',
        'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
        'algo' => 'sha1',
        'chunk_path' => $root . '.chunks',
        '_is_file_upload' => false,
    ], $overrides));
}

function makeCosChunkAdapter(string $root, array $overrides = []): CosChunkTestAdapter
{
    return new CosChunkTestAdapter(array_merge([
        'adapter' => CosAdapter::class,
        'secretId' => 'fake-ak',
        'secretKey' => 'fake-sk',
        'bucket' => 'test-bucket',
        'dirname' => 'storage',
        'domain' => 'http://cdn.example.com',
        'region' => 'ap-shanghai',
        'chunk_path' => $root . '.chunks',
        '_is_file_upload' => false,
    ], $overrides));
}

function makeS3ChunkAdapter(string $root, array $overrides = []): S3ChunkTestAdapter
{
    return new S3ChunkTestAdapter(array_merge([
        'adapter' => S3Adapter::class,
        'key' => 'fake-ak',
        'secret' => 'fake-sk',
        'bucket' => 'test-bucket',
        'dirname' => 'storage',
        'domain' => 'http://cdn.example.com',
        'region' => 'us-east-1',
        'version' => 'latest',
        'use_path_style_endpoint' => true,
        'endpoint' => 'http://s3.example.com',
        'acl' => 'public-read',
        'chunk_path' => $root . '.chunks',
        '_is_file_upload' => false,
    ], $overrides));
}

function makeQiniuChunkAdapter(string $root, array $overrides = []): QiniuChunkTestAdapter
{
    return new QiniuChunkTestAdapter(array_merge([
        'adapter' => QiniuAdapter::class,
        'accessKey' => 'fake-ak',
        'secretKey' => 'fake-sk',
        'bucket' => 'test-bucket',
        'dirname' => 'storage',
        'domain' => 'http://cdn.example.com',
        'chunk_path' => $root . '.chunks',
        '_is_file_upload' => false,
    ], $overrides));
}

test('OSS 首片上传初始化会话并记录 ETag', function () {
    $root = makeCloudChunkRoot();
    $adapter = makeOssChunkAdapter($root);
    $result = $adapter->uploadChunk(makeCloudChunkFile('aaa'), 'demo-file', 0, md5('aaa'));

    /** @var FakeOssClient $client */
    $client = $adapter->fakeClient;
    expect($client->callNames())->toBe(['initiateMultipartUpload', 'uploadPart']);
    expect($client->calls[1][1][3][OssClient::OSS_PART_NUM])->toBe(1);
    expect($result['save_path'])->toBe('storage/demo-file');
    expect($result['md5'])->toBe(md5('aaa'));

    $session = json_decode((string) file_get_contents($root . '.chunks/demo-file/session.json'), true);
    expect($session['upload_id'])->toBe('fake-upload-id');
    expect($session['object'])->toBe('storage/demo-file');
    expect($session['parts'][0]['etag'])->toBe('etag-1');
});

test('OSS 续传复用会话不重复初始化', function () {
    $adapter = makeOssChunkAdapter(makeCloudChunkRoot());
    $adapter->uploadChunk(makeCloudChunkFile('aaa'), 'demo-file', 0, md5('aaa'));
    $adapter->uploadChunk(makeCloudChunkFile('ccc'), 'demo-file', 2, md5('ccc'));

    expect(array_count_values($adapter->fakeClient->callNames())['initiateMultipartUpload'])->toBe(1);

    $progress = $adapter->chunkProgress('demo-file', 4);
    expect($progress['uploaded'])->toBe([0, 2])->and($progress['missing'])->toBe([1, 3]);
});

test('OSS 合并成功后 copy 改名并清理会话', function () {
    $root = makeCloudChunkRoot();
    $adapter = makeOssChunkAdapter($root);
    $adapter->uploadChunk(makeCloudChunkFile('aaa'), 'demo-file', 0, md5('aaa'));
    $adapter->uploadChunk(makeCloudChunkFile('bbb'), 'demo-file', 1, md5('bbb'));

    $result = $adapter->mergeChunk('demo-file', 2, 'video.mp4', ['file_hash' => 'abc123']);

    /** @var FakeOssClient $client */
    $client = $adapter->fakeClient;
    $completeArgs = $client->calls[3][1];
    expect($completeArgs[3])->toBe([
        ['PartNumber' => 1, 'ETag' => 'etag-1'],
        ['PartNumber' => 2, 'ETag' => 'etag-2'],
    ]);
    expect($client->calls[4])->toBe([
        'copyObject',
        ['test-bucket', 'storage/demo-file', 'test-bucket', 'storage/abc123.mp4'],
    ]);
    expect($client->calls[5])->toBe(['deleteObject', ['test-bucket', 'storage/demo-file']]);
    expect($result['save_path'])->toBe('storage/abc123.mp4');
    expect($result['url'])->toBe('http://cdn.example.com/storage/abc123.mp4');
    expect($result['unique_id'])->toBe('abc123');
    expect($result['size'])->toBe(6);
    expect($result['extension'])->toBe('mp4');
    expect(is_dir($root . '.chunks/demo-file'))->toBeFalse();
});

test('OSS 未传 file_hash 时使用 file_id 命名', function () {
    $adapter = makeOssChunkAdapter(makeCloudChunkRoot());
    $adapter->uploadChunk(makeCloudChunkFile('aaa'), 'demo-file', 0, md5('aaa'));

    $result = $adapter->mergeChunk('demo-file', 1, 'video.mp4');

    expect($result['save_path'])->toBe('storage/demo-file.mp4');
    expect($result['unique_id'])->toBe('demo-file');
});

test('OSS 缺片合并抛异常且不发起 complete', function () {
    $adapter = makeOssChunkAdapter(makeCloudChunkRoot());
    $adapter->uploadChunk(makeCloudChunkFile('aaa'), 'demo-file', 0, md5('aaa'));

    expect(fn() => $adapter->mergeChunk('demo-file', 3, 'demo.txt'))->toThrow(StorageException::class, '缺少分片：1,2');
    expect($adapter->fakeClient->callNames())->not->toContain('completeMultipartUpload');
});

test('云端合并校验后缀黑名单', function () {
    $adapter = makeOssChunkAdapter(makeCloudChunkRoot(), ['exclude' => ['php']]);
    $adapter->uploadChunk(makeCloudChunkFile('aaa'), 'demo-file', 0, md5('aaa'));

    expect(fn() => $adapter->mergeChunk('demo-file', 1, 'shell.php'))
        ->toThrow(StorageException::class, '文件扩展名不合法');
});

test('云端合并锁存在时抛异常', function () {
    $root = makeCloudChunkRoot();
    $adapter = makeOssChunkAdapter($root);
    $adapter->uploadChunk(makeCloudChunkFile('aaa'), 'demo-file', 0, md5('aaa'));
    file_put_contents($root . '.chunks/demo-file.lock', 'lock');

    expect(fn() => $adapter->mergeChunk('demo-file', 1, 'demo.txt'))->toThrow(StorageException::class, '正在合并中');
});

test('COS 分片全流程经 S3 风格命令完成', function () {
    $root = makeCloudChunkRoot();
    $adapter = makeCosChunkAdapter($root);
    $adapter->uploadChunk(makeCloudChunkFile('aaa'), 'demo-file', 0, md5('aaa'));
    $adapter->uploadChunk(makeCloudChunkFile('bbb'), 'demo-file', 1, md5('bbb'));

    $result = $adapter->mergeChunk('demo-file', 2, 'video.mp4', ['file_hash' => 'abc123']);

    /** @var FakeCosClient $client */
    $client = $adapter->fakeClient;
    expect($client->callNames())->toBe([
        'createMultipartUpload',
        'uploadPart',
        'uploadPart',
        'completeMultipartUpload',
        'copyObject',
        'deleteObject',
    ]);
    $completeArgs = $client->calls[3][1][0];
    expect($completeArgs['Parts'])->toBe([
        ['PartNumber' => 1, 'ETag' => 'etag-1'],
        ['PartNumber' => 2, 'ETag' => 'etag-2'],
    ]);
    $copyArgs = $client->calls[4][1][0];
    expect($copyArgs['Key'])->toBe('storage/abc123.mp4');
    expect($copyArgs['CopySource'])->toBe('test-bucket.cos.ap-shanghai.myqcloud.com/storage/demo-file');
    expect($result['url'])->toBe('http://cdn.example.com/storage/abc123.mp4');
    expect($result['size'])->toBe(6);
    expect(is_dir($root . '.chunks/demo-file'))->toBeFalse();
});

test('S3 分片全流程经 AWS 命令完成', function () {
    $root = makeCloudChunkRoot();
    $adapter = makeS3ChunkAdapter($root);
    $adapter->uploadChunk(makeCloudChunkFile('aaa'), 'demo-file', 0, md5('aaa'));
    $adapter->uploadChunk(makeCloudChunkFile('bbb'), 'demo-file', 1, md5('bbb'));

    $result = $adapter->mergeChunk('demo-file', 2, 'video.mp4');

    /** @var FakeS3Client $client */
    $client = $adapter->fakeClient;
    expect($client->callNames())->toBe([
        'createMultipartUpload',
        'uploadPart',
        'uploadPart',
        'completeMultipartUpload',
        'copyObject',
        'deleteObject',
    ]);
    $completeArgs = $client->calls[3][1][0];
    expect($completeArgs['MultipartUpload']['Parts'])->toBe([
        ['PartNumber' => 1, 'ETag' => 'etag-1'],
        ['PartNumber' => 2, 'ETag' => 'etag-2'],
    ]);
    $copyArgs = $client->calls[4][1][0];
    expect($copyArgs['Key'])->toBe('storage/demo-file.mp4');
    expect($copyArgs['CopySource'])->toBe('test-bucket/storage/demo-file');
    expect($copyArgs['ACL'])->toBe('public-read');
    expect($result['unique_id'])->toBe('demo-file');
    expect(is_dir($root . '.chunks/demo-file'))->toBeFalse();
});

test('七牛分片本地暂存后合并经 putFile 上传', function () {
    $root = makeCloudChunkRoot();
    $adapter = makeQiniuChunkAdapter($root);
    $adapter->uploadChunk(makeCloudChunkFile('aaa'), 'demo-file', 0, md5('aaa'));
    $adapter->uploadChunk(makeCloudChunkFile('bbb'), 'demo-file', 1, md5('bbb'));

    $progress = $adapter->chunkProgress('demo-file', 2);
    expect($progress['finished'])->toBeTrue();

    $result = $adapter->mergeChunk('demo-file', 2, 'video.mp4', ['file_hash' => 'abc123']);

    expect($adapter->uploads)->toBe([['storage/abc123.mp4', 'aaabbb']]);
    expect($result['save_path'])->toBe('storage/abc123.mp4');
    expect($result['url'])->toBe('http://cdn.example.com/storage/abc123.mp4');
    expect($result['size'])->toBe(6);
    expect(is_dir($root . '.chunks/demo-file'))->toBeFalse();
});
