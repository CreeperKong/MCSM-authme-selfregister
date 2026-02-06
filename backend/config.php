<?php
return [
    'environment' => getenv('APP_ENV'),
    'db' => [
        'host' => getenv('DB_HOST'),
        'port' => (int) getenv('DB_PORT'),
        'database' => getenv('DB_DATABASE'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'charset' => 'utf8mb4',
    ],
    'auth' => [
        'admin_token' => getenv('ADMIN_PANEL_TOKEN'),
    ],
    'mcsm' => [
        'base_url' => rtrim(getenv('MCSM_BASE_URL'), '/'),
        'api_key' => getenv('MCSM_API_KEY'),
        'default_daemon_id' => getenv('MCSM_DEFAULT_DAEMON_ID'),
        'default_instance_id' => getenv('MCSM_DEFAULT_INSTANCE_ID'),
        'command_template' => getenv('AUTHME_COMMAND_TEMPLATE'),
    ],
    'captcha' => [
        'provider' => getenv('CAPTCHA_PROVIDER'),
        'ttl_seconds' => (int) getenv('CAPTCHA_TTL_SECONDS'),
        'recaptcha' => [
            'site_key' => getenv('RECAPTCHA_SITE_KEY'),
            'secret_key' => getenv('RECAPTCHA_SECRET_KEY'),
        ],
        'hcaptcha' => [
            'site_key' => getenv('HCAPTCHA_SITE_KEY'),
            'secret_key' => getenv('HCAPTCHA_SECRET_KEY'),
        ],
        'turnstile' => [
            'site_key' => getenv('TURNSTILE_SITE_KEY'),
            'secret_key' => getenv('TURNSTILE_SECRET_KEY'),
        ],
    ],
    'encryption_key' => getenv('APP_ENCRYPTION_KEY'),
];
