<?php
$container = require __DIR__ . '/../bootstrap.php';
$config = $container['config'];
$database = $container['database'];
$encryption = $container['encryption'];

try {
    Http::ensureMethod(['GET', 'POST']);
    Http::requireAdminToken($config);

    $service = new RegistrationService($database->mysqli(), $encryption, null, $config);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $status = Http::query('status', 'pending');
        $items = $service->listRequests($status ?? 'pending');
        Response::success(['items' => $items]);
    }

    $payload = Http::jsonInput();
    $action = strtolower((string) ($payload['action'] ?? ''));

    if ($action === 'approve') {
        $daemonId = trim((string) ($payload['daemon_id'] ?? ''));
        $instanceId = trim((string) ($payload['instance_id'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? '')) ?: null;
        $requestId = (int) ($payload['request_id'] ?? 0);
        if (!$requestId) {
            throw new HttpException(400, '缺少请求 ID');
        }
        $mcsmService = new RegistrationService($database->mysqli(), $encryption, new MCSMClient($config['mcsm']), $config);
        $result = $mcsmService->approve($requestId, $daemonId, $instanceId, $notes);
        Response::success(['item' => $result]);
    }

    if ($action === 'reject') {
        $requestId = (int) ($payload['request_id'] ?? 0);
        $reason = trim((string) ($payload['reason'] ?? ''));
        if (!$requestId) {
            throw new HttpException(400, '缺少请求 ID');
        }
        $result = $service->reject($requestId, $reason);
        Response::success(['item' => $result]);
    }

    throw new HttpException(400, '未知操作');
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getDetails());
} catch (Throwable $e) {
    Response::error('请求处理失败：' . $e->getMessage(), 500);
}
