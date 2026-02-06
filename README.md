# MCSM AuthMe 自助注册 / 审核平台

React (前端) + PHP FastCGI (后端) + MySQL 的一体化方案，用于在 MCSManager 指定节点/实例内自动执行 `authme register` 命令。网站根目录同时包含构建后的前端静态资源与 PHP API，可直接由 Nginx/Apache 代理到 PHP-FPM 运行。

## 📋 功能特性

- **自助注册**：玩家提交用户名、邮箱、密码与补充信息，支持简单算术、人机验证（hCaptcha、reCAPTCHA v2、Cloudflare Turnstile）
- **审核后台**：管理员使用 API Token 登录，查看 `待审核/已批准/已拒绝` 列表，在线批准或拒绝；批准后自动在 MCSManager 目标实例执行命令
- **安全存储**：玩家密码使用 AES-256-GCM + `APP_ENCRYPTION_KEY` 加密，仅在执行命令时解密
- **FastCGI 友好**：所有 PHP 端点均为无状态脚本，可直接部署到常见虚拟主机面板

## 🚀 快速开始

### 1. 系统环境
- Node.js ≥ 20 + npm/pnpm/yarn（前端构建）
- PHP ≥ 8.2，启用 `pdo_mysql`、`curl`、`openssl`
- MySQL 8.0（或 MariaDB 10.5+）
- 任意支持 FastCGI 的 Web 服务器（Nginx/Apache/Caddy）

### 2. 初始化步骤

```bash
# 初始化配置（交互式向导）
php backend/setup.php

# 初始化数据库
php backend/database-init.php

# 构建前端
npm install && npm run build

# 启动开发服务器（PHP 内置服务器）
php -S localhost:8000
```

## 📁 项目结构

## 📁 项目结构

```
MCSM-authme-selfregister/
├── backend/                  # PHP API 与业务逻辑
│   ├── api/                  # 接口端点
│   │   ├── captcha.php       # 验证码接口
│   │   ├── config.php        # 配置接口
│   │   ├── daemons.php       # 守护进程接口
│   │   ├── instances.php     # 实例接口
│   │   ├── register.php      # 注册接口
│   │   ├── requests.php      # 请求管理接口
│   │   ├── save_selection.php# 保存选择接口
│   │   └── update_config.php # 更新配置接口
│   ├── lib/                  # 基础类库
│   │   ├── CaptchaVerifier.php
│   │   ├── Database.php
│   │   ├── DotEnv.php
│   │   ├── Encryption.php
│   │   ├── Http.php
│   │   ├── HttpException.php
│   │   ├── MCSMClient.php
│   │   ├── MysqlDatabase.php
│   │   ├── RegistrationService.php
│   │   └── Response.php
│   ├── bootstrap.php         # 启动脚本
│   ├── config.php            # 配置文件（自动生成）
│   ├── database-init.php     # 数据库初始化脚本
│   ├── setup.php             # 交互式配置向导
│   └── schema.sql            # 数据库 schema
├── src/                      # React 前端源代码
│   ├── pages/
│   │   ├── AdminPage.jsx
│   │   └── RegisterPage.jsx
│   ├── components/
│   │   ├── CaptchaField.jsx
│   │   └── RequestCard.jsx
│   ├── api/
│   │   └── client.js
│   ├── App.jsx
│   ├── App.css
│   ├── index.css
│   └── main.jsx
├── public/                   # 静态资源（Vite 公共目录）
├── .htaccess                 # Apache 伪静态配置
├── .env.example              # 环境变量示例
├── vite.config.js
├── package.json
└── README.md
```

## 🔧 配置与初始化

### 方案一：自动配置（推荐）

运行交互式配置向导，自动生成 `config.php` 和初始化数据库：

```bash
php backend/setup.php
```

向导会引导您完成以下配置：
- **数据库**：主机、端口、数据库名、用户名、密码
- **管理员**：Token（可自动生成）
- **加密**：应用密钥（可自动生成 32 字节 base64）
- **MCSManager**：面板地址、API Key、默认守护进程/实例 ID
- **验证码**：提供商类型、过期时间

### 方案二：手动配置

1. **复制环境变量示例**：
   ```bash
   cp .env.example .env
   ```

2. **编辑 `.env` 文件并填写配置**：

   | 变量 | 说明 | 示例 |
   | --- | --- | --- |
   | `APP_ENV` | 环境模式 | `production` / `development` |
   | `APP_TIMEZONE` | 时区 | `Asia/Shanghai` |
   | `DB_HOST` | 数据库主机 | `127.0.0.1` |
   | `DB_PORT` | 数据库端口 | `3306` |
   | `DB_DATABASE` | 数据库名 | `authme_db` |
   | `DB_USERNAME` | 数据库用户名 | `root` |
   | `DB_PASSWORD` | 数据库密码 | `` |
   | `APP_ENCRYPTION_KEY` | AES-256 加密密钥（32 字节 base64） | `` |
   | `ADMIN_PANEL_TOKEN` | 管理员 Token | `` |
   | `MCSM_BASE_URL` | MCSManager 面板地址 | `http://localhost:23333` |
   | `MCSM_API_KEY` | MCSManager API Key | `` |
   | `MCSM_DEFAULT_DAEMON_ID` | 默认守护进程 ID | `` |
   | `MCSM_DEFAULT_INSTANCE_ID` | 默认实例 ID | `` |
   | `CAPTCHA_PROVIDER` | 验证码提供商 | `simple_math` / `recaptcha_v2` / `hcaptcha` / `turnstile` |
   | `CAPTCHA_TTL_SECONDS` | 验证码过期时间 | `300` |
   | `RECAPTCHA_SITE_KEY` | reCAPTCHA Site Key | `` |
   | `RECAPTCHA_SECRET_KEY` | reCAPTCHA Secret Key | `` |
   | `HCAPTCHA_SITE_KEY` | hCaptcha Site Key | `` |
   | `HCAPTCHA_SECRET_KEY` | hCaptcha Secret Key | `` |
   | `TURNSTILE_SITE_KEY` | Turnstile Site Key | `` |
   | `TURNSTILE_SECRET_KEY` | Turnstile Secret Key | `` |

3. **初始化数据库**：
   ```bash
   php backend/database-init.php
   ```
   系统将自动创建 `captcha_challenges` 和 `registration_requests` 表。

### 数据库结构

系统使用以下主表：

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

## 💻 开发指南

### 前端开发

```bash
# 安装依赖
npm install

# 启动开发服务器
npm run dev

# 生产构建
npm run build
```

环境变量：
- `VITE_API_BASE_URL`：后端 API 地址（默认 `/backend/api`）

### 后端开发

后端代码位于 `backend/` 目录，主要包括：
- `api/`：各个接口端点
- `lib/`：核心业务逻辑类
- `bootstrap.php`：系统启动文件，负责加载 `.env` 和初始化

建议基于 `backend/lib/RegistrationService.php` 拓展业务逻辑。

## 🌐 部署

### 快速部署

```bash
# 1. 初始化配置
php backend/setup.php

# 2. 初始化数据库
php backend/database-init.php

# 3. 构建前端
npm install && npm run build

# 4. 启动 PHP-FPM（或配置 Web 服务器）
php-fpm --nodaemonize
```

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
