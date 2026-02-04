<?php
class Http
{
    public static function ensureMethod(array $allowed): void
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $allowed, true)) {
            throw new HttpException(405, 'Method Not Allowed');
        }
    }

    public static function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(400, 'Invalid JSON payload');
        }
        return $data ?? [];
    }

    public static function requireAdminToken(array $config): void
    {
        $header = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
        $expected = $config['auth']['admin_token'] ?? '';
        if (!$expected) {
            throw new HttpException(500, 'Admin token is not configured');
        }
        if (!$header || !hash_equals($expected, $header)) {
            throw new HttpException(401, 'Unauthorized');
        }
    }

    public static function query(string $key, ?string $default = null): ?string
    {
        return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
    }
}
