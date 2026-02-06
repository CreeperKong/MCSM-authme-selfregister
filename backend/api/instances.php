<?php
$container = require __DIR__ . '/../bootstrap.php';
$config = $container['config'];

try {
    Http::ensureMethod(['GET']);
    Http::requireAdminToken($config);

    $daemonId = Http::query('daemonId');
    $page = (int)(Http::query('page') ?? 1);
    $pageSize = (int)(Http::query('pageSize') ?? 20);
    
    if (!$daemonId) {
        throw new HttpException(400, '缺少 daemonId 参数');
    }

    $mcsmClient = new MCSMClient($config['mcsm']);
    $result = $mcsmClient->getInstances($daemonId, $page, $pageSize);

    // Extract and transform instance data
    // Result structure from API: { page, pageSize, maxPage, data: [...] }
    $instances = [];
    $pagination = [
        'page' => 1,
        'pageSize' => 20,
        'maxPage' => 1,
    ];

    if (isset($result['data']) && is_array($result['data'])) {
        $instances = $result['data'];
        $pagination['page'] = $result['page'] ?? 1;
        $pagination['pageSize'] = $result['pageSize'] ?? 20;
        $pagination['maxPage'] = $result['maxPage'] ?? 1;
    }

    $instances = array_map(function ($instance) {
        // Extract instance ID and name from API response
        $instanceId = $instance['instanceUuid'] ?? '';
        $instanceName = $instance['config']['nickname'] ?? 'Unknown';
        
        return [
            'id' => (string)$instanceId,
            'name' => (string)$instanceName,
            'status' => (int)($instance['status'] ?? 0),
        ];
    }, is_array($instances) ? array_filter($instances, function ($i) {
        // Filter out invalid instance records
        return !empty($i) && is_array($i) && !empty($i['instanceUuid'] ?? '');
    }) : []);

    Response::success([
        'instances' => array_values($instances),
        'pagination' => $pagination,
    ]);
} catch (HttpException $e) {
    Response::error($e->getMessage(), $e->getStatusCode(), $e->getDetails());
} catch (Throwable $e) {
    Response::error('获取实例列表失败：' . $e->getMessage(), 500);
}


