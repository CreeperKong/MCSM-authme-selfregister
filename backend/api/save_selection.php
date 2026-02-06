<?php
$container = require __DIR__ . '/../bootstrap.php';
$config = $container['config'];

try {
    Http::ensureMethod(['POST']);
    Http::requireAdminToken($config);

    $payload = Http::jsonInput();
    $daemonId = $payload['daemonId'] ?? '';
    $instanceId = $payload['instanceId'] ?? '';

    if (!$daemonId || !$instanceId) {
        throw new HttpException(400, '缺少节点或实例 ID');
    }

    // Get the .env file path
    $envPath = dirname(__DIR__, 2) . '/.env';
    
    // Read current .env content
    $envContent = '';
    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
    }

    // Update or add the configuration values
    $lines = explode("\n", $envContent);
    $daemonIdFound = false;
    $instanceIdFound = false;

    foreach ($lines as &$line) {
        if (preg_match('/^MCSM_DEFAULT_DAEMON_ID=/', $line)) {
            $line = "MCSM_DEFAULT_DAEMON_ID={$daemonId}";
            $daemonIdFound = true;
        } elseif (preg_match('/^MCSM_DEFAULT_INSTANCE_ID=/', $line)) {
            $line = "MCSM_DEFAULT_INSTANCE_ID={$instanceId}";
            $instanceIdFound = true;
        }
    }

    // Add missing configuration
    if (!$daemonIdFound) {
        $lines[] = "MCSM_DEFAULT_DAEMON_ID={$daemonId}";
    }
    if (!$instanceIdFound) {
        $lines[] = "MCSM_DEFAULT_INSTANCE_ID={$instanceId}";
    }

    // Write back to .env
    $newContent = implode("\n", $lines);
    if (!file_put_contents($envPath, $newContent)) {
        throw new HttpException(500, '无法写入 .env 文件，请检查文件权限');
    }

    Response::success([
        'message' => '配置已保存',
        'daemonId' => $daemonId,
        'instanceId' => $instanceId,
    ]);
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getDetails());
} catch (Throwable $e) {
    Response::error('保存配置失败：' . $e->getMessage(), 500);
}
