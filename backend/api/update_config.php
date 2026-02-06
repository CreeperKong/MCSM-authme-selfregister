<?php
$container = require __DIR__ . '/../bootstrap.php';
$config = $container['config'];

try {
    Http::ensureMethod(['POST']);
    Http::requireAdminToken($config);

    $payload = Http::jsonInput();
    $commandTemplate = $payload['commandTemplate'] ?? null;

    if ($commandTemplate === null) {
        throw new HttpException(400, '缺少 commandTemplate 参数');
    }

    if (empty(trim($commandTemplate))) {
        throw new HttpException(400, '命令模板不能为空');
    }

    // Validate that the template contains required placeholders
    $required = ['{username}', '{password}'];
    foreach ($required as $placeholder) {
        if (strpos($commandTemplate, $placeholder) === false) {
            throw new HttpException(400, "命令模板必须包含 $placeholder 占位符");
        }
    }

    // Save to a config override file
    $configOverridePath = dirname(__DIR__, 2) . '/.env.local';
    $overrideConfig = [];
    
    // Read existing overrides if file exists
    if (file_exists($configOverridePath)) {
        $lines = file($configOverridePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode('=', $line, 2));
            $overrideConfig[$name] = trim($value, "\"' ");
        }
    }

    // Update the command template
    $overrideConfig['AUTHME_COMMAND_TEMPLATE'] = $commandTemplate;

    // Write back to .env.local
    $lines = [];
    $lines[] = '# Auto-generated config overrides - do not edit manually';
    foreach ($overrideConfig as $name => $value) {
        // Escape and quote values with spaces or special characters
        if (preg_match('/[\s{}\[\]()]/', $value)) {
            $value = '"' . str_replace('"', '\\"', $value) . '"';
        }
        $lines[] = "$name=$value";
    }

    $newContent = implode("\n", $lines) . "\n";
    if (!file_put_contents($configOverridePath, $newContent)) {
        throw new HttpException(500, '无法写入配置文件，请检查文件权限');
    }

    Response::success([
        'message' => '命令模板已保存',
        'commandTemplate' => $commandTemplate,
    ]);
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getDetails());
} catch (Throwable $e) {
    Response::error('保存配置失败：' . $e->getMessage(), 500);
}
