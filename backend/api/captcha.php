<?php
$container = require __DIR__ . '/../bootstrap.php';
$config = $container['config'];
$captchaStore = $container['captchaStore'];

try {
    Http::ensureMethod(['GET']);
    if (($config['captcha']['provider'] ?? 'simple_math') !== 'simple_math') {
        throw new HttpException(404, '当前验证码提供商不支持手动拉取题目');
    }

    $challenge = $captchaStore->createChallenge();
    Response::success($challenge);
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getDetails());
} catch (Throwable $e) {
    Response::error('无法生成验证码：' . $e->getMessage(), 500);
}
