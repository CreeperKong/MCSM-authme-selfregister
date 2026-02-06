<?php
$container = require __DIR__ . '/../bootstrap.php';
$config = $container['config'];

try {
    Http::ensureMethod(['GET']);

    $captcha = $config['captcha'];
    $provider = $captcha['provider'] ?? 'simple_math';
    $siteKey = '';
    if ($provider === 'recaptcha_v2') {
        $siteKey = $captcha['recaptcha']['site_key'] ?? '';
    } elseif ($provider === 'hcaptcha') {
        $siteKey = $captcha['hcaptcha']['site_key'] ?? '';
    } elseif ($provider === 'turnstile') {
        $siteKey = $captcha['turnstile']['site_key'] ?? '';
    }

    Response::success([
        'captcha' => [
            'provider' => $provider,
            'siteKey' => $siteKey,
            'ttl' => $captcha['ttl_seconds'] ?? 180,
        ],
        'mcsm' => [
            'defaultDaemonId' => $config['mcsm']['default_daemon_id'] ?? '',
            'defaultInstanceId' => $config['mcsm']['default_instance_id'] ?? '',
            'commandTemplate' => getCommandTemplate() ?? 'authme register {username} {password}',
        ],
    ]);
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getDetails());
} catch (Throwable $e) {
    Response::error('配置读取失败：' . $e->getMessage(), 500);
}

function getCommandTemplate() {
    $localEnvPath = dirname(__DIR__, 2) . '/.env.local';
    if (file_exists($localEnvPath)) {
        $lines = file($localEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'AUTHME_COMMAND_TEMPLATE=')) {
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    return trim($parts[1], '"\'');
                }
            }
        }
    }
    return null;
}
