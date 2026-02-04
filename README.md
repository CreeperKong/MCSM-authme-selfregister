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
│   └── config.php        # 从 .env / 系统环境注入配置
├── public/               # 静态资源 (Vite 默认公共目录)
├── src/                  # React 前端源代码
├── .env.example          # 后端环境变量示例
├── package.json
└── vite.config.js
```

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

CREATE TABLE captcha_challenges (
	id CHAR(40) PRIMARY KEY,
	answer_hash VARCHAR(255) NOT NULL,
	expires_at DATETIME NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_expire (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 配置步骤

1. 复制示例环境变量并填写真实值：

	 ```bash
	 cp .env.example .env
	 # 生成 32 字节 base64 密钥
	 openssl rand -base64 32
	 ```

	 关键变量：

	 | 变量 | 说明 |
	 | --- | --- |
	 | `APP_ENCRYPTION_KEY` | 32 字节 base64，用于 AES-256-GCM 加密玩家密码 |
	 | `ADMIN_PANEL_TOKEN` | 管理员端口所需令牌，前端 `X-Admin-Token` 头 |
	 | `MCSM_BASE_URL` / `MCSM_API_KEY` | MCSManager 面板地址与 API Key（参考文档链接） |
	 | `MCSM_DEFAULT_DAEMON_ID` / `MCSM_DEFAULT_INSTANCE_ID` | 审核面板默认填充的节点 & 实例 |
	 | `AUTHME_COMMAND_TEMPLATE` | 默认 `authme register {username} {password} {password}`，可自定义 |
	 | `CAPTCHA_PROVIDER` | `simple_math` / `recaptcha_v2` / `hcaptcha` / `turnstile` |
	 | `*_SITE_KEY` & `*_SECRET_KEY` | 对应验证码的站点/密钥 |

2. 服务器层将 `.env` 注入 PHP 环境（或直接编辑 `backend/config.php`）。`backend/bootstrap.php` 会在运行时解析 `.env`。

3. 启动 PHP-FPM：

	 ```bash
	 php-fpm --nodaemonize
	 ```

4. Nginx 示例（将 React 构建后的静态文件与 PHP API 放在同一站点根目录）：

	 ```nginx
	 server {
		 listen 443 ssl;
		 server_name auth.example.com;
		 root /var/www/mcsm-authme-selfregister/dist;

		 location /backend/ {
			 alias /var/www/mcsm-authme-selfregister/backend/;
			 try_files $uri =404;
			 include fastcgi_params;
			 fastcgi_param SCRIPT_FILENAME $request_filename;
			 fastcgi_pass unix:/run/php/php8.2-fpm.sock;
		 }

		 location / {
			 try_files $uri /index.html;
		 }
	 }
	 ```

## 前端开发

```bash
# 安装依赖
npm install

# 开发调试
npm run dev

# 生产构建（输出至 dist/）
npm run build
```

`VITE_API_BASE_URL` 默认为 `/backend/api`，如前后端部署在不同域名，可在 `.env`（Vite）中设置 `VITE_API_BASE_URL=https://api.example.com/backend/api`。

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

切换提供商后刷新 `/admin` 与 `/` 即可；前端会自动加载对应脚本并在 `register.php` 中校验。

## 测试建议

1. 配置 `.env`，创建数据库表后运行 `php -S localhost:8081 -t backend` 测试 API。
2. 使用 `curl` 直接向 `/backend/api/register.php` 提交样例 JSON，确认验证码、数据库写入正常。
3. 在测试实例上准备 MCSManager 账户与 API Key，使用管理员面板批准请求，观察目标实例终端输出。

## 参考文档

- [MCSManager API Key 指南](https://docs.mcsmanager.com/zh_cn/apis/get_apikey.html)
- [实例 API / command 接口](https://docs.mcsmanager.com/zh_cn/apis/api_instance.html)

如需扩展（例如接入外部通知、更多状态机），建议基于 `backend/lib/RegistrationService.php` 拓展。欢迎根据自身业务调整命令模板、User-Agent 校验或引入更多审核流程。
