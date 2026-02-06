<?php
declare(strict_types=1);

/**
 * Key Generation Script
 * 
 * Usage:
 *   php backend/generate-keys.php
 * 
 * This script generates required secret keys for the application.
 */

// 只允许从命令行调用
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo '403 Forbidden: 此脚本只能从命令行运行';
    exit(1);
}

echo "🔐 生成应用密钥...\n\n";

// 生成加密密钥 (32字节)
$encryptionKey = base64_encode(random_bytes(32));
echo "✓ APP_ENCRYPTION_KEY:\n";
echo "  APP_ENCRYPTION_KEY=" . $encryptionKey . "\n\n";

// 生成管理员令牌 (32字节转十六进制)
$adminToken = bin2hex(random_bytes(32));
echo "✓ ADMIN_PANEL_TOKEN:\n";
echo "  ADMIN_PANEL_TOKEN=" . $adminToken . "\n\n";

echo "将这些值复制到 .env 文件中。\n";
echo "\n📝 建议方案：\n";
echo "1. 复制上面的行到您的 .env 文件\n";
echo "2. 确保 .env 文件安全且不提交到版本控制\n";
echo "3. 重启应用\n";
