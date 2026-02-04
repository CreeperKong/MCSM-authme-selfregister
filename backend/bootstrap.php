<?php
declare(strict_types=1);

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Shanghai');

require_once __DIR__ . '/lib/Response.php';
require_once __DIR__ . '/lib/HttpException.php';
require_once __DIR__ . '/lib/Http.php';
require_once __DIR__ . '/lib/DotEnv.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/SimpleCaptchaStore.php';
require_once __DIR__ . '/lib/CaptchaVerifier.php';
require_once __DIR__ . '/lib/Encryption.php';
require_once __DIR__ . '/lib/MCSMClient.php';
require_once __DIR__ . '/lib/RegistrationService.php';

DotEnv::load(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/config.php';

try {
    $database = new Database($config['db']);
    $encryption = new Encryption($config['encryption_key'] ?? '');
    $captchaStore = new SimpleCaptchaStore($database->pdo(), $config['captcha']['ttl_seconds'] ?? 180);
    $captchaVerifier = new CaptchaVerifier($config['captcha'], $captchaStore);
} catch (Throwable $e) {
    Response::error('初始化失败：' . $e->getMessage(), 500);
}

return [
    'config' => $config,
    'database' => $database,
    'encryption' => $encryption,
    'captchaStore' => $captchaStore,
    'captchaVerifier' => $captchaVerifier,
];
