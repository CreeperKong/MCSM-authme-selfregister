<?php
class Response
{
    public static function json(int $statusCode, array $payload = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($data = null, int $statusCode = 200): void
    {
        self::json($statusCode, [
            'status' => 'ok',
            'data' => $data,
            'time' => (int) (microtime(true) * 1000),
        ]);
    }

    public static function error(string $message, int $statusCode = 400, array $details = []): void
    {
        self::json($statusCode, [
            'status' => 'error',
            'message' => $message,
            'details' => $details,
        ]);
    }
}
