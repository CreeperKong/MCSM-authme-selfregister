# MCSM AuthMe 自助注册系统 - Apache 部署指南

本文档提供了在共享主机或虚拟主机环境（如 cPanel、Plesk 等）中部署此应用的详细步骤。

## 文件结构

```
public_html/（或 www/ 或其他Web根目录）
├── .htaccess              # 伪静态配置（自动路由）
├── index.html             # React 应用入口（来自 npm run build 的 dist/ 输出）
├── assets/                # 静态资源（CSS、JS、图片等）
├── backend/               # PHP 后端 API
│   ├── setup.php          # 配置向导（仅 CLI）
│   ├── database-init.php  # 数据库初始化（仅 CLI）
│   ├── bootstrap.php
│   ├── config.php         # 配置文件（自动生成）
│   ├── config.example.php # 配置示例
│   ├── schema.sql         # 数据库 schema
│   ├── lib/               # 业务逻辑类
│   └── api/               # API 端点
│       ├── config.php
│       ├── register.php
│       ├── requests.php
│       └── captcha.php
└── ...其他静态资源文件
```

## 一键部署步骤

### 1. 上传文件到服务器

使用 FTP/SFTP 将所有文件上传到 Web 根目录（通常是 `public_html` 或 `www`）。

```bash
# 本地打包（在项目根目录）
npm run build           # 生成 dist/ 目录
tar czf mcsm-authme.tar.gz dist/ backend/ .htaccess *.md

# 上传后解压
tar xzf mcsm-authme.tar.gz

# 或者直接上传到服务器（推荐使用 git clone）
git clone https://github.com/CreeperKong/MCSM-authme-selfregister.git
cd MCSM-authme-selfregister
npm run build
cp -r dist/* ../public_html/
cp -r backend/ ../public_html/
cp .htaccess ../public_html/
```

### 2. 创建配置文件

通过 SSH/后台终端运行配置向导：

```bash
cd public_html
php backend/setup.php
```

按照提示填写：
- 💾 **数据库信息** - 从虚拟主机控制面板获取（通常是 localhost）
- 🔐 **管理员 Token** - 一个强随机密钥（建议自动生成）
- 🔑 **加密密钥** - 用于加密用户密码（建议自动生成）
- 🎮 **MCSManager 配置** - 与游戏服务器对接的参数

### 3. 初始化数据库

```bash
php backend/database-init.php
```

脚本会自动创建所需的数据表。

### 4. 设置文件权限（重要！）

```bash
# 确保 .htaccess 存在且可读
chmod 644 .htaccess

# 确保 config.php 只有 PHP 可读（安全考虑）
chmod 600 backend/config.php

# 后端目录可执行
chmod 755 backend/
```

### 5. 验证配置

访问应用：

```
https://yourdomain.com/              # 应该显示注册页面
https://yourdomain.com/backend/api/config.php  # 应该返回 JSON 响应
https://yourdomain.com/admin/        # 应该显示管理员页面
```

## 故障排查

### 问题：404 错误 / 页面加载失败

**原因**：`.htaccess` 未启用或 `mod_rewrite` 未安装

**解决方案**：
1. 检查 `.htaccess` 是否上传（隐藏文件，需要在 FTP 客户端启用"显示隐藏文件"）
2. 在虚拟主机控制面板验证：
   - ✅ `mod_rewrite` 已启用
   - ✅ AllowOverride 设置为 "All" 或 "FileInfo"
3. 如无法修改虚拟主机配置，联系服务商

**临时方案**（如果 .htaccess 不可用）：
```php
// 在 frontend/vite.config.js 中配置
export default {
  build: {
    outDir: 'dist',
  },
  server: {
    proxy: {
      '/backend/api': 'http://localhost/backend/api'
    }
  }
}
```

### 问题：500 错误

**原因**：
1. PHP 版本不符（需要 >= 8.2）
2. 数据库连接失败
3. 缺少必要的 PHP 扩展（`pdo_mysql`、`curl`）

**解决方案**：
1. 检查虚拟主机 PHP 版本：`php -v`
2. 验证数据库凭证：编辑 `backend/config.php`
3. 确保扩展已启用：`php -m | grep -E 'pdo|curl'`

### 问题：数据库错误

**原因**：MySQL 连接失败或表未创建

**解决方案**：
1. 验证 config.php 中的数据库凭证
2. 重新运行初始化：`php backend/database-init.php`
3. 在虚拟主机控制面板的 phpMyAdmin 中验证数据库和用户

## 环境变量配置

### 方案 A：通过 `.env` 文件（推荐）

在项目根目录创建 `.env` 文件（与 config.php 同级）：

```bash
cp .env.example .env
# 编辑文件，填写环境变量
nano .env
```

示例 `.env`：
```
APP_ENV=production
APP_TIMEZONE=Asia/Shanghai

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=mcsm_authme
DB_USERNAME=mcsm_user
DB_PASSWORD=your_secure_password

APP_ENCRYPTION_KEY=base64_encoded_32_byte_key
ADMIN_PANEL_TOKEN=your_admin_token

MCSM_BASE_URL=https://mcsmanager.example.com
MCSM_API_KEY=your_mcsm_api_key
MCSM_DEFAULT_DAEMON_ID=daemon_id
MCSM_DEFAULT_INSTANCE_ID=instance_id

CAPTCHA_PROVIDER=simple_math
CAPTCHA_TTL_SECONDS=180
```

### 方案 B：通过 VirtualHost 环境变量

在虚拟主机配置中设置：

```apache
<VirtualHost *:443>
    # ... 其他配置 ...
    
    # 设置环境变量
    SetEnv DB_HOST localhost
    SetEnv DB_DATABASE mcsm_authme
    SetEnv APP_ENCRYPTION_KEY "your_key_here"
    SetEnv ADMIN_PANEL_TOKEN "your_token_here"
</VirtualHost>
```

## 安全建议

### 1. 保护敏感文件

`.htaccess` 已包含以下保护：

```apache
# 禁止直接访问这些文件
<FilesMatch "\.env|\.git|config\.example\.php|schema\.sql|setup\.php|database-init\.php">
    Require all denied
</FilesMatch>
```

### 2. HTTPS 配置

```apache
# 强制 HTTPS
<IfModule mod_rewrite.c>
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>
```

### 3. CORS 配置（如跨域）

```apache
# 允许特定域的跨域请求
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "https://trusted-domain.com"
    Header set Access-Control-Allow-Methods "GET, POST, OPTIONS"
</IfModule>
```

### 4. 定期备份

定期备份数据库：

```bash
# 使用 mysqldump
mysqldump -u mcsm_user -p mcsm_authme > backup.sql

# 或在虚拟主机控制面板中设置自动备份
```

## Nginx 用户

如果服务器使用 Nginx，`.htaccess` 不适用，改用以下配置：

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name auth.example.com;
    root /var/www/mcsm-authme-selfregister;
    
    # SSL 配置...
    
    # PHP API 路由
    location /backend/api/ {
        try_files $uri =404;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # React SPA 路由
    location / {
        try_files $uri /index.html;
    }
    
    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|icons|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # 防止访问敏感文件
    location ~ "\.(env|git|example\.php|sql)$" {
        deny all;
    }
}
```

## 常见问题

**Q: 可以在 Windows IIS 上运行吗？**  
A: 可以，但需要使用 `web.config` 而不是 `.htaccess`。

**Q: Node.js 是否必须安装？**  
A: 不需要。只需在本地构建，然后将 `dist/` 目录上传到服务器。

**Q: 能在子目录中运行吗？**  
A: 可以。编辑 `.htaccess` 的 `RewriteBase /` 改为 `RewriteBase /subdir/`。

**Q: 如何更新应用？**  
A: 使用 git pull，然后 `npm run build` 并上传 `dist/` 目录覆盖现有文件。
