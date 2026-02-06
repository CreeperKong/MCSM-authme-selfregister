<?php
class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_AUTOCOMMIT => true,
        ]);
        error_log('Database: 数据库连接已建立，autocommit=' . ($this->pdo->getAttribute(PDO::ATTR_AUTOCOMMIT) ? 'true' : 'false'));
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
