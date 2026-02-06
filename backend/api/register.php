<?php
$container = require __DIR__ . '/../bootstrap.php';
$config = $container['config'];
$database = $container['database'];
$captchaVerifier = $container['captchaVerifier'];
$encryption = $container['encryption'];

try {
    Http::ensureMethod(['POST']);
    $payload = Http::jsonInput();

    error_log('注册API: 接收到请求，payload keys=' . implode(',', array_keys($payload)));
    
    $captchaPayload = $payload['captcha'] ?? [];
    error_log('注册API: 开始验证验证码，captcha payload=' . json_encode($captchaPayload));
    $captchaVerifier->verify($captchaPayload, $_SERVER['REMOTE_ADDR'] ?? null);
    error_log('注册API: 验证码验证通过');

    $service = new RegistrationService($database->mysqli(), $encryption, null, $config);
    $result = $service->createRequest($payload, $_SERVER['REMOTE_ADDR'] ?? null);

    Response::success($result, 201);
} catch (HttpException $e) {
    error_log('注册API HttpException: ' . $e->getMessage());
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getDetails());
} catch (Throwable $e) {
    error_log('注册API 异常: ' . $e->getMessage() . ' (' . get_class($e) . ')');
    error_log('异常堆栈: ' . $e->getTraceAsString());
    Response::error('注册失败：' . $e->getMessage(), 500);
}
