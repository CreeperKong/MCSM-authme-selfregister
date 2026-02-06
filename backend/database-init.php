<?php
declare(strict_types=1);

/**
 * Database Initialization Script
 * 
 * Usage:
 *   php backend/database-init.php
 * 
 * This script initializes the database tables required by the system.
 */

// 只允许从命令行调用
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo '403 Forbidden: 此脚本只能从命令行运行';
    exit(1);
}

require_once __DIR__ . '/lib/DotEnv.php';
require_once __DIR__ . '/lib/Database.php';

DotEnv::load(dirname(__DIR__) . '/.env');
$config = require __DIR__ . '/config.php';

try {
    echo "🔄 初始化数据库...\n";
    
    // 检查 config.php 是否存在
    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        throw new Exception('config.php 不存在。请先从 config.example.php 复制并配置好 config.php');
    }
    
    $database = new Database($config['db']);
    $pdo = $database->pdo();
    
    // Read schema file
    $schemaPath = __DIR__ . '/schema.sql';
    if (!file_exists($schemaPath)) {
        throw new Exception('schema.sql 文件不存在');
    }
    
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new Exception('无法读取 schema.sql 文件');
    }
    
    // Split by semicolon and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        fn($stmt) => !empty($stmt)
    );
    
    $executedCount = 0;
    foreach ($statements as $statement) {
        // Remove single-line comments (-- comment)
        $lines = explode("\n", $statement);
        $cleanLines = [];
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            // Skip empty lines and comment lines
            if (!empty($trimmedLine) && !str_starts_with($trimmedLine, '--')) {
                $cleanLines[] = $line;
            }
        }
        
        $sql = trim(implode("\n", $cleanLines));
        
        if (!empty($sql)) {
            echo "执行: " . substr($sql, 0, 50) . "...\n";
            try {
                $pdo->exec($sql);
                $executedCount++;
            } catch (PDOException $e) {
                throw new Exception("SQL 执行错误: " . $e->getMessage() . "\n语句: " . $sql);
            }
        }
    }
    
    if ($executedCount === 0) {
        throw new Exception('未执行任何 SQL 语句，请检查 schema.sql 文件内容');
    }
    
    echo "\n✅ 数据库初始化成功！\n";
    echo "📊 执行了 $executedCount 条 SQL 语句\n";
    echo "✓ captcha_challenges 表已创建\n";
    echo "✓ registration_requests 表已创建\n";
    
} catch (Throwable $e) {
    echo "❌ 初始化失败：" . $e->getMessage() . "\n";
    exit(1);
}
