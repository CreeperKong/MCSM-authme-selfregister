<?php
return [
    'environment' => getenv('APP_ENV') ?: 'production',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_DATABASE') ?: 'mcsm_authme',
        'username' => getenv('DB_USERNAME') ?: 'mcsm_authme',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'auth' => [
        'admin_token' => getenv('ADMIN_PANEL_TOKEN') ?: '',
    ],
    'mcsm' => [
        'base_url' => rtrim(getenv('MCSM_BASE_URL') ?: 'https://panel.example.com', '/'),
        'api_key' => getenv('MCSM_API_KEY') ?: '',
        'default_daemon_id' => getenv('MCSM_DEFAULT_DAEMON_ID') ?: '',
        'default_instance_id' => getenv('MCSM_DEFAULT_INSTANCE_ID') ?: '',
        'command_template' => getenv('AUTHME_COMMAND_TEMPLATE') ?: 'authme register {username} {password} {password}',
    ],
    'captcha' => [
        'provider' => getenv('CAPTCHA_PROVIDER') ?: 'simple_math',
        'ttl_seconds' => (int) (getenv('CAPTCHA_TTL_SECONDS') ?: 180),
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
    'encryption_key' => getenv('APP_ENCRYPTION_KEY') ?: '',
];
