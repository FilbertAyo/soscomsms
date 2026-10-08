<?php

return [

    'welcome_credits' => (int) env('SMS_WELCOME_CREDITS', 20),

    'provider_unit_cost_tzs' => (int) env('SMS_PROVIDER_UNIT_COST_TZS', 14),

    'min_purchase_quantity' => 50,

    'packages' => [50, 1000, 5000],

    'generic_sender_ids' => ['HARUSI', 'SEND OFF', 'MWALIKO', 'MCHANGO'],

    'sender_id_max_length' => 13,

    'sender_id_blocklist' => [
        'CRDB',
        'NMB',
        'NBC',
        'EQUITY',
        'KCB',
        'STANBIC',
        'ABSA',
        'BARCLAYS',
        'BOA',
        'DTB',
        'EXIM',
        'PBZ',
        'TCB',
        'AZANIA',
        'ACCESS',
        'UBA',
        'CITIBANK',
        'STANCHART',
        'FINCA',
        'TPB',
        'ACB',
        'DCBBANK',
        'BOT',
        'TCRA',
        'TRA',
        'MOBISHASTRA',
    ],

    'rate_limits' => [
        'dashboard_send' => (int) env('SMS_DASHBOARD_SEND_RATE_LIMIT', 30),
    ],

    'send_unit_price_tzs' => (int) env('SMS_SEND_UNIT_PRICE_TZS', 30),

    'max_recipients_per_campaign' => 5000,

    'gateway' => env('SMS_GATEWAY', 'fake'),

    'provider' => [
        'profile_id' => env('SMS_PROVIDER_PROFILE_ID'),
        'password' => env('SMS_PROVIDER_PASSWORD'),
        'base_url' => env('SMS_PROVIDER_BASE_URL', 'https://mshastra.com'),
        'static_ip' => env('SMS_PROVIDER_STATIC_IP'),
        'country_code' => env('SMS_PROVIDER_COUNTRY_CODE', 'ALL'),
        'send_path' => env('SMS_PROVIDER_SEND_PATH', 'sendurl.aspx'),
        'json_path' => env('SMS_PROVIDER_JSON_PATH', 'sendsms_api_json.aspx'),
        'balance_path' => env('SMS_PROVIDER_BALANCE_PATH', 'balance.aspx'),
        'dlr_path' => env('SMS_PROVIDER_DLR_PATH', 'dlrstatus_api.aspx'),
        'dlr_base_url' => env('SMS_PROVIDER_DLR_BASE_URL'),
        'timeout' => (int) env('SMS_PROVIDER_TIMEOUT', 20),
        'bulk_chunk' => (int) env('SMS_PROVIDER_BULK_CHUNK', 100),
        // Seconds before a stuck "sending" delivery (no provider id) may be retried.
        'retry_stale_after' => (int) env('SMS_PROVIDER_RETRY_STALE_AFTER', 90),
        // Soft time budget per dispatch/resume run so cron can finish and continue next minute.
        'dispatch_budget_seconds' => (int) env('SMS_PROVIDER_DISPATCH_BUDGET', 45),
        'low_balance_threshold' => (int) env('SMS_PROVIDER_LOW_BALANCE', 10000),
        'dlr_token' => env('SMS_PROVIDER_DLR_TOKEN'),
        // Each minute, check up to this many messages, oldest-checked first, within the time budget.
        'dlr_batch' => (int) env('SMS_PROVIDER_DLR_BATCH', 200),
        'dlr_budget_seconds' => (int) env('SMS_PROVIDER_DLR_BUDGET', 40),
        'dlr_timeout' => (int) env('SMS_PROVIDER_DLR_TIMEOUT', 8),
        // Do not re-check the same message more often than this.
        'dlr_recheck_seconds' => (int) env('SMS_PROVIDER_DLR_RECHECK', 60),
        // Stop polling messages that still have no final status after this many hours.
        'dlr_max_age_hours' => (int) env('SMS_PROVIDER_DLR_MAX_AGE_HOURS', 72),
    ],

    'fake' => [
        'balance' => (int) env('SMS_FAKE_BALANCE', 100000),
        'fail_phones' => [],
        'timeout_phones' => [],
        'fail_dlr_phones' => [],
        'auto_dlr' => (bool) env('SMS_FAKE_AUTO_DLR', true),
    ],

    'api' => [
        'key_prefix' => env('SMS_API_KEY_PREFIX', 'sk_live_'),
        'rate_limit' => (int) env('SMS_API_RATE_LIMIT', 60),
        'max_keys' => (int) env('SMS_API_MAX_KEYS', 10),
    ],

    'webhooks' => [
        'enabled' => (bool) env('SMS_WEBHOOKS_ENABLED', false),
        'timeout' => (int) env('SMS_WEBHOOK_TIMEOUT', 10),
        'max_per_customer' => (int) env('SMS_WEBHOOK_MAX', 5),
        'max_attempts' => (int) env('SMS_WEBHOOK_ATTEMPTS', 5),
        'backoff' => [60, 300, 900, 3600],
    ],

    'snippe' => [
        'api_key' => env('SNIPPE_API_KEY'),
        'webhook_secret' => env('SNIPPE_WEBHOOK_SECRET'),
        'base_url' => rtrim((string) env('SNIPPE_BASE_URL', 'https://api.snippe.sh'), '/'),
    ],

    'production' => [
        'app_url' => 'https://sms.soscom.co.tz',
        'backup_keep_days' => (int) env('SMS_BACKUP_KEEP_DAYS', 14),
        'low_balance_alert_hours' => (int) env('SMS_LOW_BALANCE_ALERT_HOURS', 12),
    ],

];
