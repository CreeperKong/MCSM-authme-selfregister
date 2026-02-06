# MCSM AuthMe 自助注册 / 审核平台

React (前端) + PHP FastCGI (后端) + MySQL 的一体化方案，用于在 MCSManager 指定节点/实例内自动执行 `authme register` 命令。网站根目录同时包含构建后的前端静态资源与 PHP API，可直接由 Nginx/Apache 代理到 PHP-FPM 运行。

## 功能速览

- 自助注册：玩家提交用户名、邮箱、密码与补充信息，支持简单算术、人机验证（hCaptcha、reCAPTCHA v2、Cloudflare Turnstile）。
- 审核后台：管理员使用 API Key 登录，查看 `待审核/已批准/已拒绝` 列表，在线批准或拒绝；批准后自动在 MCSManager 目标实例执行命令。
- 安全存储：玩家明文密码使用 AES-256-GCM + `APP_ENCRYPTION_KEY` 加密，仅在执行命令时解密。
- FastCGI 友好：所有 PHP 端点均为无状态脚本，可直接部署到常见面板或与 Vite 构建产物共同发布。

## 目录结构

```
├── backend/              # PHP API 与业务逻辑
│   ├── api/              # register.php / requests.php / config.php / captcha.php
│   ├── lib/              # Database、Captcha、MCSManager client 等基础类
│   ├── bootstrap.php
│   ├── database-init.php # 数据库初始化脚本（CLI 专用）
│   ├── setup.php         # 配置向导脚本（CLI 专用）
│   ├── config.php        # 配置文件（自动生成）
│   ├── config.example.php # 配置示例
│   └── schema.sql        # 数据库 schema
├── public/               # 静态资源 (Vite 默认公共目录)
├── src/                  # React 前端源代码
├── .htaccess             # Apache 伪静态配置
├── .env.example          # 后端环境变量示例
├── DEPLOYMENT.md         # 详细部署指南（虚拟主机/cPanel/Plesk）
├── package.json
└── vite.config.js
```

📖 **详细部署指南**：见 [DEPLOYMENT.md](DEPLOYMENT.md) - 适用于 cPanel、Plesk 等虚拟主机环境

## 基础环境

- Node.js ≥ 20 + npm/pnpm/yarn（前端构建）
- PHP ≥ 8.2，启用 `pdo_mysql`、`curl`, `openssl`
- MySQL 8.0（或 MariaDB 10.5+）
- 任意支持 FastCGI 的 Web 服务器（Nginx/Apache/Caddy 等）

## 数据库结构

```sql
CREATE TABLE registration_requests (
	id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	username VARCHAR(32) NOT NULL,
	email VARCHAR(190) NOT NULL,
	password_hash VARCHAR(255) NOT NULL,
	password_payload TEXT NOT NULL,
	note TEXT NULL,
	admin_notes TEXT NULL,
	status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
	requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	processed_at TIMESTAMP NULL DEFAULT NULL,
	processed_by VARCHAR(100) NULL,
	mcsm_daemon_id VARCHAR(64) NULL,
	mcsm_instance_id VARCHAR(64) NULL,
	rejection_reason TEXT NULL,
	ip_address VARCHAR(64) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 数据库初始化

在首次启动系统前，需要初始化数据库表结构。

### 方案一：自动初始化（推荐）

直接运行初始化脚本（确保 MySQL 连接配置已在 `.env` 或环境变量中）：

```bash
php backend/database-init.php
```

系统将自动创建 `captcha_challenges` 和 `registration_requests` 表。

### 方案二：手动初始化

也可以直接导入 SQL 文件：

```bash
mysql -h127.0.0.1 -u{username} -p{password} {database} < backend/schema.sql
```

## 配置步骤

### 快速配置（推荐）

运行交互式配置向导，自动生成 config.php：

```bash
php backend/setup.php
```

向导会引导您完成以下配置项：
- **数据库**：主机、端口、数据库名、用户名、密码
- **管理员**：Token（可自动生成）
- **加密**：应用密钥（可自动生成 32 字节 base64）
- **MCSManager**：地址、API Key、默认守护进程/实例 ID
- **验证码**：提供商类型（simple_math/recaptcha_v2/hcaptcha/turnstile）、过期时间

### 手动配置

1. 复制示例配置文件：

	 ```bash
	 cp backend/config.example.php backend/config.php
	 ```

2. 编辑 `backend/config.php` 并填写相关配置：

	 | 配置项 | 说明 |
	 | --- | --- |
	 | `db.host` | MySQL 数据库主机 |
	 | `db.port` | MySQL 数据库端口（默认 3306） |
	 | `db.database` | 数据库名称 |
	 | `db.username` | 数据库用户名 |
	 | `db.password` | 数据库密码 |
	 | `auth.admin_token` | 管理员 API Token（需传入 `X-Admin-Token` 头） |
	 | `encryption_key` | AES-256-GCM 加密密钥（32 字节 base64） |
	 | `mcsm.base_url` | MCSManager 面板地址 |
	 | `mcsm.api_key` | MCSManager API Key |
	 | `mcsm.default_daemon_id` | 默认守护进程 ID |
	 | `mcsm.default_instance_id` | 默认实例 ID |
	 | `captcha.provider` | 验证码提供商（simple_math/recaptcha_v2/hcaptcha/turnstile） |
	 | `captcha.ttl_seconds` | 验证码有效期（秒） |

	 如需使用高级验证码（reCAPTCHA/hCaptcha/Turnstile），也可配置对应的 `site_key` 和 `secret_key`。

### 初始化数据库

配置完成后，初始化数据库表：

```bash
php backend/database-init.php
```

系统将自动创建：
- `captcha_challenges` - 验证码表
- `registration_requests` - 注册请求表

## 环境变量配置

系统也支持通过 `.env` 文件传入环境变量覆盖 config.php：

```bash
cp .env.example .env
```

可设置的环境变量包括：

| 变量 | 说明 |
| --- | --- |
| `APP_ENV` | 环境模式（production/development） |
| `APP_TIMEZONE` | 时区（默认 Asia/Shanghai） |
| `DB_HOST` | 数据库主机 |
| `DB_PORT` | 数据库端口 |
| `DB_DATABASE` | 数据库名称 |
| `DB_USERNAME` | 数据库用户名 |
| `DB_PASSWORD` | 数据库密码 |
| `APP_ENCRYPTION_KEY` | 加密密钥 |
| `ADMIN_PANEL_TOKEN` | 管理员 Token |
| `MCSM_BASE_URL` | MCSManager 地址 |
| `MCSM_API_KEY` | MCSManager API Key |
| `MCSM_DEFAULT_DAEMON_ID` | 默认守护进程 ID |
| `MCSM_DEFAULT_INSTANCE_ID` | 默认实例 ID |
| `AUTHME_COMMAND_TEMPLATE` | AuthMe 命令模板 |
| `CAPTCHA_PROVIDER` | 验证码提供商 |
| `CAPTCHA_TTL_SECONDS` | 验证码过期时间（秒） |
| `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` | reCAPTCHA v2 密钥 |
| `HCAPTCHA_SITE_KEY` / `HCAPTCHA_SECRET_KEY` | hCaptcha 密钥 |
| `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile 密钥 |

`backend/bootstrap.php` 在运行时会自动加载 `.env` 文件并注入环境变量。

## 前端开发

```bash
# 安装依赖
npm install

# 开发调试
npm run dev

# 生产构建（输出至 dist/）
npm run build
```

`VITE_API_BASE_URL` 默认为 `/backend/api`，如前后端部署在不同域名，可配置环境变量覆盖。

## 部署

### 快速部署流程

```bash
# 1. 初始化配置（交互式向导）
php backend/setup.php

# 2. 初始化数据库
php backend/database-init.php

# 3. 构建前端（可选，如果已有 node 环境）
npm install && npm run build

# 4. 启动 PHP-FPM
php-fpm --nodaemonize
```

### 虚拟主机/cPanel/Plesk 用户

如果使用共享主机或虚拟主机面板，请参考详细指南：**[DEPLOYMENT.md](DEPLOYMENT.md)**

该文档包含：
- ✅ 一键部署步骤
- ✅ Apache `.htaccess` 配置说明
- ✅ 虚拟主机控制面板设置
- ✅ 故障排查指南

### Nginx 配置示例

```nginx
server {
    listen 443 ssl;
    server_name auth.example.com;
    
    # SSL 证书配置（可选）
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    # 设置站点根目录
    root /var/www/mcsm-authme-selfregister;
    index index.html index.php;
    
    # PHP API 路由
    location /backend/api/ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
    
    # React SPA 路由
    location / {
        try_files $uri /index.html;
    }
    
    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Apache 配置示例

```apache
<VirtualHost *:443>
    ServerName auth.example.com
    DocumentRoot /var/www/mcsm-authme-selfregister
    
    # 启用 SSL（可选）
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
    
    # PHP API 处理
    <Location /backend/api>
        SetHandler application/x-httpd-php
    </Location>
    
    # React SPA 路由（需启用 mod_rewrite）
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </IfModule>
</VirtualHost>
```

### .htaccess 配置（简化版）

如果使用 Apache 但没有 VirtualHost 访问权限，可以在项目根目录放置 `.htaccess` 文件来实现伪静态配置。项目已自动生成了完整的 `.htaccess` 文件，功能包括：

- ✅ **React SPA 路由** - 将所有非文件/目录的请求转发到 `index.html`
- ✅ **PHP API 优先级** - `/backend/api/` 请求直接由 PHP 处理
- ✅ **静态资源缓存** - 设置长期缓存策略（CSS/JS/图片/字体等）
- ✅ **Gzip 压缩** - 自动压缩 HTML、CSS、JavaScript 等文本资源
- ✅ **安全防护** - 禁止直接访问敏感文件（`.env`、`setup.php` 等）
- ✅ **UTF-8 编码** - 确保正确的字符集设置

**配置位置**：`/.htaccess`

**启用条件**：
1. Apache 服务器（确保启用 `mod_rewrite`）
2. `.htaccess` 文件在项目根目录
3. 虚拟主机 AllowOverride 配置允许 `.htaccess`（通常默认允许）

```apache
# 在 VirtualHost 中确保允许 .htaccess 覆盖
<Directory /var/www/mcsm-authme-selfregister>
    AllowOverride All
</Directory>
```

## 后端接口摘要

| 方法 | URL | 描述 |
| --- | --- | --- |
| `GET /backend/api/config.php` | 获取前端需要的验证码、MCSManager 默认值 |
| `GET /backend/api/captcha.php` | 仅 `simple_math` 模式下获取算术题目 |
| `POST /backend/api/register.php` | 玩家提交注册请求（含 captcha） |
| `GET /backend/api/requests.php?status=pending` | 管理员查看请求，需 `X-Admin-Token` |
| `POST /backend/api/requests.php` | `{"action":"approve",...}` 或 `{"action":"reject",...}` |

所有响应格式一致：

```json
{
	"status": "ok",
	"data": { ... },
	"time": 1733347200000
}
```

错误时返回：

```json
{
	"status": "error",
	"message": "描述",
	"details": { "response": "可选" }
}
```

## 管理员工作流

1. 登录 `/admin` 页面，粘贴 `ADMIN_PANEL_TOKEN`（实际建议使用独立、复杂的随机字符串）。
2. 查看待审核请求，填写节点/实例（默认值取自 `.env`），可添加管理员备注。
3. 点击“批准并执行”即会向 `MCSManager` 发送 `GET /api/protected_instance/command` 请求。若失败，错误会展示在面板顶部。
4. 拒绝请求需要填写理由，方便回溯。

## Captcha 选项

- `simple_math`：内置算术题（推荐在内网/无外网依赖场景）。
- `recaptcha_v2`：加载 Google 脚本，需可访问 `www.google.com`。
- `hcaptcha`：隐私友好地区推荐。
- `turnstile`：Cloudflare 免费验证码。

⚠️ 服务器端始终以 `.env` 中的 `CAPTCHA_PROVIDER` 为准，前端提交的字段仅携带令牌与答案，无法通过伪造 `provider` 绕过验证。

切换提供商后刷新 `/admin` 与 `/` 即可；前端会自动加载对应脚本并在 `register.php` 中校验。

## 测试建议

1. 配置 `.env`，创建数据库表后运行 `php -S localhost:8081 -t backend` 测试 API。
2. 使用 `curl` 直接向 `/backend/api/register.php` 提交样例 JSON，确认验证码、数据库写入正常。
3. 在测试实例上准备 MCSManager 账户与 API Key，使用管理员面板批准请求，观察目标实例终端输出。

## 参考文档

- [MCSManager API Key 指南](https://docs.mcsmanager.com/zh_cn/apis/get_apikey.html)
- [实例 API / command 接口](https://docs.mcsmanager.com/zh_cn/apis/api_instance.html)

如需扩展（例如接入外部通知、更多状态机），建议基于 `backend/lib/RegistrationService.php` 拓展。欢迎根据自身业务调整命令模板、User-Agent 校验或引入更多审核流程。
