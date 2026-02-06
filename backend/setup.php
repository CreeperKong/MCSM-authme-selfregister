<?php
declare(strict_types=1);

/**
 * Setup Configuration Script
 * 
 * 自动创建和配置 config.php
 * 
 * Usage:
 *   php backend/setup.php
 */

// 只允许从命令行调用
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo '403 Forbidden: 此脚本只能从命令行运行';
    exit(1);
}

$configPath = __DIR__ . '/config.php';
$examplePath = __DIR__ . '/config.example.php';

// ANSI 颜色代码
const COLOR_RESET = "\033[0m";
const COLOR_BOLD = "\033[1m";
const COLOR_GREEN = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_CYAN = "\033[36m";

function colorize(string $text, string $color): string
{
    return $color . $text . COLOR_RESET;
}

function readInput(string $prompt, string $default = ''): string
{
    echo colorize($prompt, COLOR_CYAN);
    if ($default) {
        echo " [" . colorize($default, COLOR_YELLOW) . "]";
    }
    echo ": ";
    
    $input = trim(fgets(STDIN));
    return $input === '' ? $default : $input;
}

function generateEncryptionKey(int $bytes = 32): string
{
    return base64_encode(random_bytes($bytes));
}

function generateAdminToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

try {
    echo "\n" . colorize("=== MCSM AuthMe 自助注册系统 - 配置向导 ===\n", COLOR_BOLD);
    
    // 检查 config.example.php 存在
    if (!file_exists($examplePath)) {
        throw new Exception('config.example.php 不存在');
    }
    
    // 如果 config.php 存在，询问是否覆盖
    if (file_exists($configPath)) {
        echo colorize("\n⚠️  config.php 已存在", COLOR_YELLOW) . "\n";
        $overwrite = readInput('是否覆盖现有配置? (y/n)', 'n');
        if (strtolower($overwrite) !== 'y') {
            echo colorize("✓ 取消操作", COLOR_GREEN) . "\n\n";
            exit(0);
        }
    }
    
    // 读取示例配置
    $exampleConfig = require $examplePath;
    
    // 交互式配置
    echo "\n" . colorize("数据库配置", COLOR_BOLD) . "\n";
    $dbHost = readInput("数据库主机", $exampleConfig['db']['host']);
    $dbPort = readInput("数据库端口", (string)$exampleConfig['db']['port']);
    $dbDatabase = readInput("数据库名称", $exampleConfig['db']['database']);
    $dbUsername = readInput("数据库用户名", $exampleConfig['db']['username']);
    $dbPassword = readInput("数据库密码", "");
    
    echo "\n" . colorize("管理员配置", COLOR_BOLD) . "\n";
    $generateToken = readInput("自动生成管理员令牌? (y/n)", 'y');
    if (strtolower($generateToken) === 'y') {
        $adminToken = generateAdminToken();
        echo colorize("✓ 已生成: " . $adminToken, COLOR_GREEN) . "\n";
    } else {
        $adminToken = readInput("管理员令牌（X-Admin-Token）", "");
    }
    
    echo "\n" . colorize("加密配置", COLOR_BOLD) . "\n";
    $generateKey = readInput("自动生成加密密钥? (y/n)", 'y');
    if (strtolower($generateKey) === 'y') {
        $encryptionKey = generateEncryptionKey();
        echo colorize("✓ 已生成 32 字节 base64 密钥", COLOR_GREEN) . "\n";
    } else {
        $encryptionKey = readInput("加密密钥（APP_ENCRYPTION_KEY）", "");
    }
    
    echo "\n" . colorize("MCSManager 配置", COLOR_BOLD) . "\n";
    $mcsmBaseUrl = readInput("MCSManager 地址", $exampleConfig['mcsm']['base_url']);
    $mcsmApiKey = readInput("MCSManager API Key", "");
    $mcsmDaemonId = readInput("默认守护进程 ID", $exampleConfig['mcsm']['default_daemon_id']);
    $mcsmInstanceId = readInput("默认实例 ID", $exampleConfig['mcsm']['default_instance_id']);
    
    echo "\n" . colorize("验证码配置", COLOR_BOLD) . "\n";
    echo "可选值: simple_math, recaptcha_v2, hcaptcha, turnstile\n";
    $captchaProvider = readInput("验证码提供商", $exampleConfig['captcha']['provider']);
    $captchaTtl = readInput("验证码过期时间(秒)", (string)$exampleConfig['captcha']['ttl_seconds']);
    
    // 生成配置内容
    $config = [
        'environment' => getenv('APP_ENV') ?: 'production',
        'db' => [
            'host' => $dbHost,
            'port' => (int)$dbPort,
            'database' => $dbDatabase,
            'username' => $dbUsername,
            'password' => $dbPassword,
            'charset' => 'utf8mb4',
        ],
        'auth' => [
            'admin_token' => $adminToken,
        ],
        'mcsm' => [
            'base_url' => rtrim($mcsmBaseUrl, '/'),
            'api_key' => $mcsmApiKey,
            'default_daemon_id' => $mcsmDaemonId,
            'default_instance_id' => $mcsmInstanceId,
            'command_template' => getenv('AUTHME_COMMAND_TEMPLATE') ?: 'authme register {username} {password} {password}',
        ],
        'captcha' => [
            'provider' => $captchaProvider,
            'ttl_seconds' => (int)$captchaTtl,
            'recaptcha' => [
                'site_key' => getenv('RECAPTCHA_SITE_KEY') ?: '',
                'secret_key' => getenv('RECAPTCHA_SECRET_KEY') ?: '',
            ],
            'hcaptcha' => [
                'site_key' => getenv('HCAPTCHA_SITE_KEY') ?: '',
                'secret_key' => getenv('HCAPTCHA_SECRET_KEY') ?: '',
            ],
            'turnstile' => [
                'site_key' => getenv('TURNSTILE_SITE_KEY') ?: '',
                'secret_key' => getenv('TURNSTILE_SECRET_KEY') ?: '',
            ],
        ],
        'encryption_key' => $encryptionKey,
    ];
    
    // 生成 PHP 代码
    $configCode = "<?php\n";
    $configCode .= "/**\n";
    $configCode .= " * Application Configuration\n";
    $configCode .= " * Auto-generated by setup.php\n";
    $configCode .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
    $configCode .= " * \n";
    $configCode .= " * To reinitialize, run: php backend/setup.php\n";
    $configCode .= " * To initialize database, run: php backend/database-init.php\n";
    $configCode .= " */\n\n";
    $configCode .= "return " . var_export($config, true) . ";\n";
    
    // 写入文件
    if (file_put_contents($configPath, $configCode) === false) {
        throw new Exception("无法写入 config.php");
    }
    
    // 设置文件权限（Linux/Mac）
    if (PHP_OS_FAMILY !== 'Windows') {
        chmod($configPath, 0600);
    }
    
    echo "\n" . colorize("✅ 配置已保存到 config.php", COLOR_GREEN) . "\n\n";
    echo colorize("下一步:", COLOR_BOLD) . "\n";
    echo "  1. 运行数据库初始化: php backend/database-init.php\n";
    echo "  2. 前端开发: npm install && npm run dev\n";
    echo "  3. 前端生产构建: npm run build\n\n";
    
} catch (Throwable $e) {
    echo "\n" . colorize("❌ 配置失败: " . $e->getMessage(), COLOR_YELLOW) . "\n\n";
    exit(1);
}
