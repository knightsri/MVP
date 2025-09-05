<?php
// Configuration variables with better organization
$config = [
    'webhook' => [
        'url' => 'https://your-n8n-webhook-url.com/endpoint', // Replace with your n8n webhook URL
        'content_type' => 'image/png', // Default content type
        'timeout' => 30, // cURL timeout in seconds
        'max_retries' => 3, // Maximum retry attempts
    ],
    'uploads' => [
        'directory' => 'uploads/',
        'cache_time' => 3600, // Cache time in seconds (1 hour)
        'security_file' => '.htaccess',
        'security_content' => "deny from all\n",
        'permissions' => 0755,
        'file_permissions' => 0644,
    ],
    'validation' => [
        'max_filename_length' => 255,
        'sanitization_filters' => ['null_bytes', 'control_chars', 'script_tags'],
    ]
];

// Security configuration - move to constants
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif'
]);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// State constants
define('STATE_FORM', 'form');
define('STATE_UPLOADED', 'uploaded');
define('STATE_PROCESSED', 'processed');

// Action constants
define('ACTION_RESET', 'reset');
define('ACTION_UPLOAD', 'upload');
define('ACTION_TRYON', 'tryon');

// Error handling configuration
define('MAX_ERROR_MSG_LENGTH', 200);
define('ENABLE_ERROR_LOGGING', true);
define('ERROR_LOG_FILE', 'logs/errors.log');

// Application constants
define('APP_TITLE', 'Jewelry Try-On Application');
define('MAX_CURL_TIMEOUT', 60);
define('PIN_STATE_ON', 'on');
define('PIN_STATE_OFF', 'off');

// Image optimization constants
define('IMAGE_MAX_WIDTH', 1200);
define('IMAGE_MAX_HEIGHT', 1200);
define('IMAGE_JPEG_QUALITY', 85);
define('IMAGE_PNG_COMPRESSION', 6);
define('IMAGE_WEBP_QUALITY', 80);
define('IMAGE_BACKUP_ORIGINAL', true);

// Debugging configuration
define('DEBUG_ENABLED', true); // Set to true to enable forced debug logging
define('DEBUG_FILE', '/full/path/to/php_error_debug.log'); // Full path to the forced debug log file

// Create required directories
$required_dirs = [
    $config['uploads']['directory'],
    dirname(ERROR_LOG_FILE),
    dirname(DEBUG_FILE) // Ensure debug log directory is also created
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, $config['uploads']['permissions'], true)) {
            log_error("Failed to create directory: $dir", 'CONFIG');
        }
    }
}

// Ensure uploads directory security
$security_file = $config['uploads']['directory'] . $config['uploads']['security_file'];
if (!file_exists($security_file)) {
    if (file_put_contents($security_file, $config['uploads']['security_content']) === false) {
        log_error("Failed to create security file: $security_file", 'CONFIG');
    }
}
?>
