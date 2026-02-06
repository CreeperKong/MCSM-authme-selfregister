<?php
declare(strict_types=1);

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Shanghai');

require_once __DIR__ . '/lib/Response.php';
require_once __DIR__ . '/lib/HttpException.php';
require_once __DIR__ . '/lib/Http.php';
require_once __DIR__ . '/lib/DotEnv.php';
require_once __DIR__ . '/lib/MysqlDatabase.php';
require_once __DIR__ . '/lib/CaptchaVerifier.php';
require_once __DIR__ . '/lib/Encryption.php';
require_once __DIR__ . '/lib/MCSMClient.php';
require_once __DIR__ . '/lib/RegistrationService.php';

DotEnv::load(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/config.php';

// 验证必需的环境变量
$required = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'ADMIN_PANEL_TOKEN', 'MCSM_BASE_URL', 'MCSM_API_KEY', 'AUTHME_COMMAND_TEMPLATE', 'CAPTCHA_PROVIDER', 'CAPTCHA_TTL_SECONDS', 'APP_ENCRYPTION_KEY'];
$missing = [];
foreach ($required as $var) {
    if (!getenv($var)) {
        $missing[] = $var;
    }
}
if (!empty($missing)) {
    throw new Exception('缺少必需的环境变量：' . implode(', ', $missing) . '。请复制 .env.example 到 .env 并填入配置。');
}

try {
    $database = new MysqlDatabase($config['db']);
    $encryption = new Encryption($config['encryption_key'] ?? '');
    $captchaVerifier = new CaptchaVerifier($config['captcha']);
} catch (Throwable $e) {
    Response::error('初始化失败：' . $e->getMessage(), 500);
}

return [
    'config' => $config,
    'database' => $database,
    'encryption' => $encryption,
    'captchaVerifier' => $captchaVerifier,
];
