<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security-related configuration options for the
    | application including input validation, rate limiting, and security
    | headers configuration.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Input Sanitization
    |--------------------------------------------------------------------------
    |
    | Configuration for input sanitization and validation.
    |
    */

    'input_sanitization' => [
        'enabled' => env('INPUT_SANITIZATION_ENABLED', true),
        'log_suspicious' => env('LOG_SUSPICIOUS_INPUT', true),
        'max_text_length' => env('MAX_TEXT_LENGTH', 10000),
        'max_name_length' => env('MAX_NAME_LENGTH', 255),
        'max_email_length' => env('MAX_EMAIL_LENGTH', 255),
        
        // Allowed file types for uploads
        'allowed_file_types' => explode(',', env('ALLOWED_FILE_TYPES', 'pdf,doc,docx,jpg,jpeg,png')),
        'max_file_size' => env('MAX_FILE_SIZE', 5120), // KB
        
        // Restricted email domains
        'restricted_email_domains' => [
            'tempmail.com', '10minutemail.com', 'guerrillamail.com',
            'mailinator.com', 'throwaway.email', 'temp-mail.org',
            'getnada.com', 'maildrop.cc', 'sharklasers.com',
            'yopmail.com', 'mohmal.com', 'emailondeck.com'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Detailed configuration for API rate limiting with Redis backend.
    |
    */

    'rate_limiting' => [
        'enabled' => env('RATE_LIMITING_ENABLED', true),
        'use_redis' => env('RATE_LIMITING_USE_REDIS', true),
        'log_violations' => env('LOG_RATE_LIMIT_VIOLATIONS', true),
        
        'limits' => [
            'api' => [
                'requests_per_minute' => env('THROTTLE_REQUESTS_PER_MINUTE', 60),
                'burst_limit' => env('THROTTLE_API_BURST_LIMIT', 10),
            ],
            'login' => [
                'attempts_per_minute' => env('THROTTLE_LOGIN_ATTEMPTS', 5),
                'attempts_per_hour' => env('THROTTLE_LOGIN_ATTEMPTS_HOUR', 20),
                'lockout_duration' => env('LOGIN_LOCKOUT_DURATION', 900), // 15 minutes
            ],
            'admin' => [
                'requests_per_minute' => env('THROTTLE_ADMIN_REQUESTS_PER_MINUTE', 30),
                'global_per_minute' => env('THROTTLE_ADMIN_GLOBAL_PER_MINUTE', 100),
                'bulk_operations_per_minute' => env('THROTTLE_BULK_REQUESTS_PER_MINUTE', 5),
            ],
            'uploads' => [
                'requests_per_minute' => env('THROTTLE_UPLOAD_REQUESTS_PER_MINUTE', 10),
                'requests_per_hour' => env('THROTTLE_UPLOAD_REQUESTS_PER_HOUR', 50),
            ],
            'registration' => [
                'requests_per_minute' => env('THROTTLE_REGISTRATION_PER_MINUTE', 3),
                'requests_per_hour' => env('THROTTLE_REGISTRATION_PER_HOUR', 10),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HTTP security headers.
    |
    */

    'headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        
        'csp' => [
            'enabled' => env('CSP_ENABLED', true),
            'report_only' => env('CSP_REPORT_ONLY', false),
            'report_uri' => env('CSP_REPORT_URI', null),
            
            'directives' => [
                'default-src' => "'self'",
                'script-src' => "'self' 'unsafe-inline' 'unsafe-eval'",
                'style-src' => "'self' 'unsafe-inline'",
                'img-src' => "'self' data: https:",
                'font-src' => "'self'",
                'connect-src' => "'self'",
                'media-src' => "'self'",
                'object-src' => "'none'",
                'child-src' => "'none'",
                'worker-src' => "'none'",
                'frame-ancestors' => "'none'",
                'base-uri' => "'self'",
                'form-action' => "'self'",
            ],
        ],
        
        'hsts' => [
            'enabled' => env('HSTS_ENABLED', true),
            'max_age' => env('HSTS_MAX_AGE', 31536000), // 1 year
            'include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', true),
            'preload' => env('HSTS_PRELOAD', true),
        ],
        
        'permissions_policy' => [
            'enabled' => env('PERMISSIONS_POLICY_ENABLED', true),
            'directives' => [
                'geolocation' => '()',
                'microphone' => '()',
                'camera' => '()',
                'payment' => '()',
                'usb' => '()',
                'accelerometer' => '()',
                'gyroscope' => '()',
                'magnetometer' => '()',
                'midi' => '()',
                'notifications' => '()',
                'push' => '()',
                'speaker' => '()',
                'vibrate' => '()',
                'fullscreen' => '()',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Security
    |--------------------------------------------------------------------------
    |
    | Security settings for authentication and session management.
    |
    */

    'authentication' => [
        'session_timeout' => env('SESSION_TIMEOUT', 120), // minutes
        'token_expiration' => env('TOKEN_EXPIRATION', null), // null = no expiration
        'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
        'lockout_duration' => env('LOGIN_LOCKOUT_DURATION', 900), // seconds
        
        'password_requirements' => [
            'min_length' => env('PASSWORD_MIN_LENGTH', 8),
            'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
            'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
            'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
            'require_symbols' => env('PASSWORD_REQUIRE_SYMBOLS', true),
        ],
        
        'two_factor' => [
            'enabled' => env('TWO_FACTOR_ENABLED', false),
            'required_for_admin' => env('TWO_FACTOR_REQUIRED_ADMIN', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    |
    | Security settings for file uploads and storage.
    |
    */

    'file_uploads' => [
        'enabled' => env('FILE_UPLOADS_ENABLED', true),
        'max_size' => env('MAX_FILE_SIZE', 5120), // KB
        'allowed_types' => explode(',', env('ALLOWED_FILE_TYPES', 'pdf,doc,docx,jpg,jpeg,png')),
        'scan_for_viruses' => env('SCAN_UPLOADS_FOR_VIRUSES', false),
        'quarantine_suspicious' => env('QUARANTINE_SUSPICIOUS_FILES', true),
        
        'storage' => [
            'disk' => env('UPLOAD_DISK', 'public'),
            'path' => env('UPLOAD_PATH', 'uploads'),
            'organize_by_date' => env('ORGANIZE_UPLOADS_BY_DATE', true),
            'organize_by_user' => env('ORGANIZE_UPLOADS_BY_USER', true),
        ],
        
        'validation' => [
            'check_mime_type' => env('CHECK_FILE_MIME_TYPE', true),
            'check_file_extension' => env('CHECK_FILE_EXTENSION', true),
            'check_file_content' => env('CHECK_FILE_CONTENT', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging and Monitoring
    |--------------------------------------------------------------------------
    |
    | Security logging and monitoring configuration.
    |
    */

    'logging' => [
        'security_events' => env('LOG_SECURITY_EVENTS', true),
        'failed_logins' => env('LOG_FAILED_LOGINS', true),
        'rate_limit_violations' => env('LOG_RATE_LIMIT_VIOLATIONS', true),
        'suspicious_input' => env('LOG_SUSPICIOUS_INPUT', true),
        'admin_actions' => env('LOG_ADMIN_ACTIONS', true),
        'file_uploads' => env('LOG_FILE_UPLOADS', true),
        
        'channels' => [
            'security' => env('SECURITY_LOG_CHANNEL', 'daily'),
            'audit' => env('AUDIT_LOG_CHANNEL', 'daily'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Filtering and Geolocation
    |--------------------------------------------------------------------------
    |
    | IP-based security filtering configuration.
    |
    */

    'ip_filtering' => [
        'enabled' => env('IP_FILTERING_ENABLED', false),
        'whitelist' => explode(',', env('IP_WHITELIST', '')),
        'blacklist' => explode(',', env('IP_BLACKLIST', '')),
        'block_tor' => env('BLOCK_TOR_EXITS', false),
        'block_proxies' => env('BLOCK_PROXIES', false),
        
        'geolocation' => [
            'enabled' => env('GEOLOCATION_ENABLED', false),
            'allowed_countries' => explode(',', env('ALLOWED_COUNTRIES', '')),
            'blocked_countries' => explode(',', env('BLOCKED_COUNTRIES', '')),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Security
    |--------------------------------------------------------------------------
    |
    | Database security configuration.
    |
    */

    'database' => [
        'query_logging' => env('LOG_DATABASE_QUERIES', false),
        'slow_query_threshold' => env('SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'connection_encryption' => env('DB_ENCRYPT', false),
        'ssl_verify' => env('DB_SSL_VERIFY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Security
    |--------------------------------------------------------------------------
    |
    | API-specific security configuration.
    |
    */

    'api' => [
        'versioning' => [
            'enabled' => env('API_VERSIONING_ENABLED', true),
            'default_version' => env('API_DEFAULT_VERSION', 'v1'),
            'supported_versions' => explode(',', env('API_SUPPORTED_VERSIONS', 'v1')),
        ],
        
        'cors' => [
            'enabled' => env('CORS_ENABLED', true),
            'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
            'allowed_methods' => explode(',', env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS')),
            'allowed_headers' => explode(',', env('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With')),
            'credentials' => env('CORS_SUPPORTS_CREDENTIALS', true),
        ],
        
        'documentation' => [
            'enabled' => env('API_DOCS_ENABLED', true),
            'require_auth' => env('API_DOCS_REQUIRE_AUTH', false),
        ],
    ],

];