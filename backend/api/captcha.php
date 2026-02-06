<?php
$container = require __DIR__ . '/../bootstrap.php';
$config = $container['config'];
$captchaStore = $container['captchaStore'];

try {
    Http::ensureMethod(['GET']);
    throw new HttpException(404, '当前验证码提供商不支持手动拉取题目');

    error_log('验证码API: 开始创建验证码');
    $challenge = $captchaStore->createChallenge();
    error_log('验证码API: 验证码创建成功，ID=' . $challenge['id']);
    Response::success($challenge);
} catch (HttpException $e) {
    error_log('验证码API HttpException: ' . $e->getMessage());
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getDetails());
} catch (Throwable $e) {
    error_log('验证码API 异常: ' . $e->getMessage() . ' (' . get_class($e) . ')');
    error_log('异常堆栈: ' . $e->getTraceAsString());
    Response::error('无法生成验证码：' . $e->getMessage(), 500);
}
