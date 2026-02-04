<?php
class MCSMClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
        $this->apiKey = $config['api_key'] ?? '';
        if (!$this->baseUrl || !$this->apiKey) {
            throw new RuntimeException('MCSM_BASE_URL 与 MCSM_API_KEY 必须配置');
        }
    }

    public function sendCommand(string $daemonId, string $instanceId, string $command): array
    {
        if (!$daemonId || !$instanceId) {
            throw new HttpException(400, '缺少节点或实例 ID');
        }

        $endpoint = sprintf('%s/api/protected_instance/command', $this->baseUrl);
        $query = http_build_query([
            'apikey' => $this->apiKey,
            'daemonId' => $daemonId,
            'uuid' => $instanceId,
            'command' => $command,
        ]);
        $url = sprintf('%s?%s', $endpoint, $query);
        return $this->request('GET', $url);
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'X-Requested-With: XMLHttpRequest',
        ];

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $options[CURLOPT_POSTFIELDS] = $json;
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new HttpException(502, '无法连接到 MCSManager', ['error' => $error]);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || ($decoded['status'] ?? 500) >= 400) {
            throw new HttpException(502, 'MCSManager 返回异常', ['response' => $decoded, 'status' => $status]);
        }

        return $decoded;
    }
}
