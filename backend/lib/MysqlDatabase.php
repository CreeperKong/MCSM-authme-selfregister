<?php
class MysqlDatabase
{
    private mysqli $mysqli;

    public function __construct(array $config)
    {
        // 使用 mysqli，它会自动使用 mysqlnd 驱动
        $this->mysqli = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port'] ?? 3306
        );

        if ($this->mysqli->connect_error) {
            throw new Exception('数据库连接失败：' . $this->mysqli->connect_error);
        }

        // 设置字符集
        $charset = $config['charset'] ?? 'utf8mb4';
        $this->mysqli->set_charset($charset);
        
        error_log('MysqlDatabase: 数据库连接成功，使用 mysqlnd 驱动');
    }

    public function mysqli(): mysqli
    {
        return $this->mysqli;
    }

    public function close(): void
    {
        if (isset($this->mysqli)) {
            $this->mysqli->close();
        }
    }
}
