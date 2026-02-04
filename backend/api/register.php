<?php
$container = require __DIR__ . '/../bootstrap.php';
$config = $container['config'];
$database = $container['database'];
$captchaVerifier = $container['captchaVerifier'];
$encryption = $container['encryption'];

try {
    Http::ensureMethod(['POST']);
    $payload = Http::jsonInput();

    $captchaPayload = $payload['captcha'] ?? [];
    $captchaVerifier->verify($captchaPayload, $_SERVER['REMOTE_ADDR'] ?? null);

    $service = new RegistrationService($database->pdo(), $encryption, null, $config);
    $result = $service->createRequest($payload, $_SERVER['REMOTE_ADDR'] ?? null);

    Response::success($result, 201);
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getDetails());
} catch (Throwable $e) {
    Response::error('注册失败：' . $e->getMessage(), 500);
}
