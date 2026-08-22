<?php
declare(strict_types=1);

use Tinywan\Storage\Traits\ErrorMsg;
use Tinywan\Storage\Traits\StaticErrorMsg;

$errorMsg = new class {
    use ErrorMsg;
};

test('setError 记录错误信息并返回成功标记', function () use ($errorMsg) {
    expect($errorMsg->setError(false, '上传失败', ['id' => 1]))
        ->toBeFalse()
        ->and($errorMsg->getError())
        ->toBe([
            'message' => '上传失败',
            'data' => ['id' => 1],
        ])
        ->and($errorMsg->getMessage())
        ->toBe('上传失败');
});

test('returnData 返回统一响应结构', function () use ($errorMsg) {
    expect($errorMsg->returnData(true, 'ok', 200, [
        'k' => 'v',
    ]))->toBe([
        'success' => true,
        'message' => 'ok',
        'code' => 200,
        'data' => ['k' => 'v'],
    ]);
});

$staticErrorMsg = new class {
    use StaticErrorMsg;
};

test('静态错误消息读写', function () use ($staticErrorMsg) {
    expect($staticErrorMsg::getStaticMessage())
        ->toBe('success')
        ->and($staticErrorMsg::setStaticError(false, '静态错误'))
        ->toBeFalse()
        ->and($staticErrorMsg::getStaticMessage())
        ->toBe('静态错误');

    $staticErrorMsg::$message = 'success';
});
