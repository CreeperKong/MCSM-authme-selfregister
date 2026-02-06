<?php
$container = require __DIR__ . '/../bootstrap.php';
$config = $container['config'];

try {
    Http::ensureMethod(['GET']);
    Http::requireAdminToken($config);

    $mcsmClient = new MCSMClient($config['mcsm']);
    $daemons = $mcsmClient->getDaemons();

    // Transform daemon data for frontend
    $daemons = array_map(function ($daemon) {
        // Extract from dashboard API response structure
        $daemonId = $daemon['uuid'] ?? '';
        $daemonName = $daemon['remarks'] ?? 'Unknown';
        $available = $daemon['available'] ?? false;
        
        return [
            'id' => (string)$daemonId,
            'name' => (string)$daemonName,
            'available' => (bool)$available,
        ];
    }, is_array($daemons) ? array_filter($daemons, function ($d) {
        // Filter out invalid daemon records and ensure uuid exists
        return !empty($d) && is_array($d) && !empty($d['uuid'] ?? '');
    }) : []);

    Response::success([
        'daemons' => array_values($daemons),
    ]);
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getDetails());
} catch (Throwable $e) {
    Response::error('获取节点列表失败：' . $e->getMessage(), 500);
}

