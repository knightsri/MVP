<?php
/**
 * Error handling and logging functions
 */

// Function to log errors with context
function log_error($message, $context = 'GENERAL', $severity = 'ERROR') {
    if (!ENABLE_ERROR_LOGGING) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $request_uri = $_SERVER['REQUEST_URI'] ?? 'unknown';

    $log_entry = sprintf(
        "[%s] [%s] [%s] [%s] [%s] %s\n",
        $timestamp,
        $severity,
        $context,
        $ip,
        $user_agent,
        $message
    );

    $log_dir = dirname(ERROR_LOG_FILE);
    if (!is_dir($log_dir) && !mkdir($log_dir, 0755, true)) {
        error_log("Could not create log directory: $log_dir");
        return;
    }

    file_put_contents(ERROR_LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

// Function to create user-friendly error messages
function create_user_error($tech_error, $user_message, $context = '') {
    // Log the technical error
    log_error($tech_error, $context, 'ERROR');

    // Return user-friendly message
    return substr($user_message, 0, MAX_ERROR_MSG_LENGTH);
}

// Function to handle exceptions and errors
function handle_exception($exception, $context = 'GENERAL') {
    $error_msg = $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine();
    log_error($error_msg, $context, 'EXCEPTION');

    return create_user_error(
        $error_msg,
        "An unexpected error occurred. Please try again later.",
        $context
    );
}

/**
 * Input validation and sanitization functions
 */

// Function to sanitize string inputs
function sanitize_string($input, $context = '', $max_length = 0) {
    global $config;

    if (!is_string($input)) {
        log_error("Non-string input provided to sanitize_string", $context, 'WARNING');
        return '';
    }

    // Remove null bytes and dangerous characters
    $sanitized = str_replace(array("\0", "\r", "\n"), '', $input);

    // Remove script tags and other common XSS vectors
    $sanitized = filter_var($sanitized, FILTER_SANITIZE_SPECIAL_CHARS);

    // Trim whitespace
    $sanitized = trim($sanitized);

    // Limit length if specified
    if ($max_length > 0 && strlen($sanitized) > $max_length) {
        $sanitized = substr($sanitized, 0, $max_length);
        log_error("Input trimmed due to length limit: $max_length", $context, 'WARNING');
    }

    return $sanitized;
}

// Function to validate and sanitize checkbox/pin state
function validate_pin_state($input, $context = '') {
    $sanitized = sanitize_string($input, $context, 10);

    if ($sanitized === PIN_STATE_ON || $sanitized === PIN_STATE_OFF) {
        return $sanitized;
    }

    log_error("Invalid pin state: $input", $context, 'WARNING');
    return PIN_STATE_OFF;
}

// Function to validate action parameter
function validate_action($action, $context = '') {
    $valid_actions = [ACTION_RESET, ACTION_UPLOAD, ACTION_TRYON];

    if (in_array($action, $valid_actions)) {
        return $action;
    }

    log_error("Invalid action attempted: $action", $context, 'WARNING');
    return null;
}

// Function to validate file paths for security
function validate_file_path($path, $context = '') {
    global $config;

    if (empty($path)) {
        return null;
    }

    $path = sanitize_string($path, $context);
    $real_path = realpath($path);

    // Check if file exists and is readable
    if (!$real_path || !is_readable($real_path)) {
        log_error("Invalid or unreadable file path: $path", $context, 'WARNING');
        return null;
    }

    // Ensure it's within the uploads directory
    $uploads_real_path = realpath($config['uploads']['directory']);
    if (strpos($real_path, $uploads_real_path) !== 0) {
        log_error("File path outside uploads directory: $path", $context, 'SECURITY');
        return null;
    }

    return $real_path;
}

/**
 * File upload and handling functions (enhanced)
 */

// Function to generate a random filename with original extension
function generate_random_filename($original_name) {
    global $config;

    $original_name = sanitize_string($original_name, 'FILENAME_GEN');
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    // Validate extension against allowed types
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        log_error("Attempted to generate filename for unsupported extension: $extension", 'FILENAME_GEN', 'WARNING');
        $extension = 'png'; // Default fallback
    }

    // Generate secure random filename with timestamp
    $timestamp = microtime(true);
    $random = bin2hex(random_bytes(8));
    $filename = sprintf('img_%s_%s.%s', date('Ymd_His', $timestamp), $random, $extension);

    return $filename;
}

// Function to validate uploaded files (enhanced with better error handling)
function validate_file_upload($file) {
    if (!is_array($file)) {
        return [
            'valid' => false,
            'error' => create_user_error(
                'Invalid file array structure',
                'There was a problem with your file upload. Please try again.',
                'FILE_VALIDATION'
            )
        ];
    }

    // Check if file upload was successful
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_msg = get_upload_error_message($file['error']);
        return [
            'valid' => false,
            'error' => create_user_error(
                "Upload error code: {$file['error']}",
                $error_msg,
                'FILE_VALIDATION'
            )
        ];
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return [
            'valid' => false,
            'error' => create_user_error(
                "File size {$file['size']} exceeds limit",
                'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.',
                'FILE_VALIDATION'
            )
        ];
    }

    // Validate file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return [
            'valid' => false,
            'error' => create_user_error(
                "Unsupported extension: $extension",
                'Invalid file type. Only ' . implode(', ', ALLOWED_EXTENSIONS) . ' files are allowed.',
                'FILE_VALIDATION'
            )
        ];
    }

    // Get real MIME type from file content (more secure than relying on browser)
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return [
            'valid' => false,
            'error' => create_user_error(
                'Fileinfo extension not available',
                'System configuration error. Please contact support.',
                'FILE_VALIDATION'
            )
        ];
    }

    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Validate MIME type
    if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
        return [
            'valid' => false,
            'error' => create_user_error(
                "MIME type detected: $mime_type",
                'Invalid file format detected. Please ensure you\'re uploading a valid image.',
                'FILE_VALIDATION'
            )
        ];
    }

    // Additional check: verify file is actually an image
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return [
            'valid' => false,
            'error' => create_user_error(
                'Image validation failed',
                'File is not a valid image. Please upload a proper image file.',
                'FILE_VALIDATION'
            )
        ];
    }

    return ['valid' => true, 'error' => null];
}

// Function to get user-friendly upload error messages
function get_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File is too large. Please choose a smaller file.';
        case UPLOAD_ERR_PARTIAL:
            return 'File upload was interrupted. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded. Please select a file.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'System configuration error. Please contact support.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to save the uploaded file. Please try again.';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload was blocked by security settings.';
        default:
            return 'An error occurred during file upload. Please try again.';
    }
}

/**
 * File serving functions (enhanced with validation)
 */

// Function to serve files securely
function serve_secure_file($file_path) {
    global $config;

    try {
        // Validate file path to prevent directory traversal
        $validated_path = validate_file_path($file_path, 'FILE_SERVING');

        if (!$validated_path) {
            return null;
        }

        return $validated_path;
    } catch (Exception $e) {
        log_error($e->getMessage(), 'FILE_SERVING', 'EXCEPTION');
        return null;
    }
}

// Function to sanitize and validate user-provided file paths
function sanitize_file_path($posted_path, $uploads_dir) {
    global $config;

    // Remove any null bytes or dangerous characters
    $clean_path = str_replace(array("\0", "\r", "\n"), '', $posted_path);

    // Get the filename (basename) to prevent directory traversal
    $filename = sanitize_string(basename($clean_path), 'PATH_SANITIZATION');

    // Validate that filename doesn't contain dangerous sequences
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        log_error("Dangerous characters in filename: $filename", 'PATH_SANITIZATION', 'SECURITY');
        return null; // Invalid path
    }

    // Validate filename length
    if (strlen($filename) > $config['validation']['max_filename_length']) {
        log_error("Filename too long: $filename", 'PATH_SANITIZATION', 'WARNING');
        return null;
    }

    // Construct safe path within uploads directory
    $safe_path = sanitize_string($uploads_dir, 'PATH_SANITIZATION') . $filename;

    // Validate the real path is within uploads directory
    $real_path = realpath($safe_path);
    if (!$real_path) {
        log_error("Real path not found: $safe_path", 'PATH_SANITIZATION', 'WARNING');
        return null;
    }

    $uploads_real_path = realpath(__DIR__ . '/' . dirname($uploads_dir . '/'));

    if (!$uploads_real_path || strpos($real_path, $uploads_real_path) !== 0) {
        log_error("Path traversal attempt: $real_path", 'PATH_SANITIZATION', 'SECURITY');
        return null;
    }

    return $safe_path;
}

/**
 * Webhook communication functions
 */

function call_webhook_with_retry($user_photo_path, $jewelry_photo_path, $max_retries = 3) {
    global $config;

    $errors = [];

    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        try {
            $result = call_webhook($user_photo_path, $jewelry_photo_path);

            if ($result['success']) {
                return $result;
            }

            $errors[] = $result['error'];
            log_error("Webhook attempt $attempt failed: {$result['error']}", 'WEBHOOK', 'WARNING');

        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            log_error("Exception on webhook attempt $attempt: " . $e->getMessage(), 'WEBHOOK', 'ERROR');
        }

        // Wait before retry (exponential backoff)
        if ($attempt < $max_retries) {
            sleep($attempt);
        }
    }

    return [
        'success' => false,
        'error' => create_user_error(
            'All webhook attempts failed: ' . implode('; ', $errors),
            'Unable to process your request. Please try again in a few moments.',
            'WEBHOOK'
        ),
        'response' => null
    ];
}

function call_webhook($user_photo_path, $jewelry_photo_path) {
    global $config;

    try {
        // Validate input paths
        $user_path = validate_file_path($user_photo_path, 'WEBHOOK_CALL');
        $jewelry_path = validate_file_path($jewelry_photo_path, 'WEBHOOK_CALL');

        if (!$user_path || !$jewelry_path) {
            return [
                'success' => false,
                'error' => 'Invalid file paths provided for webhook call',
                'response' => null
            ];
        }

        // Prepare cURL request
        $ch = curl_init();

        if (!$ch) {
            throw new Exception('Failed to initialize cURL');
        }

        curl_setopt($ch, CURLOPT_URL, $config['webhook']['url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['webhook']['timeout']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // Prepare multipart data with files and text prompt
        $user_filename = basename($user_path);
        $jewelry_filename = basename($jewelry_path);

        if (!file_exists($user_path) || !file_exists($jewelry_path)) {
            throw new Exception('Source files not found');
        }

        $mime_user = mime_content_type($user_path);
        $mime_jewelry = mime_content_type($jewelry_path);

        $postData = [
            'user_photo' => new CURLFile($user_path, $mime_user, $user_filename),
            'jewelry_photo' => new CURLFile($jewelry_path, $mime_jewelry, $jewelry_filename),
            'prompt' => 'Try on this jewelry bracelet on the user\'s wrist',
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        // Execute the request
        $response = curl_exec($ch);

        if ($response === false) {
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            curl_close($ch);
            throw new Exception("cURL error [{$curl_errno}]: {$curl_error}");
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("HTTP error: {$http_code}");
        }

        log_error("Webhook call successful", 'WEBHOOK', 'INFO');

        return [
            'success' => true,
            'error' => null,
            'response' => $response
        ];

    } catch (Exception $e) {
        log_error("Webhook call exception: " . $e->getMessage(), 'WEBHOOK', 'ERROR');
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'response' => null
        ];
    }
}

/**
 * Session state management functions
 */

// Function to initialize session state securely
function initialize_session_state() {
    return [
        'state' => STATE_FORM,
        'user_photo_path' => '',
        'jewelry_photo_path' => '',
        'tryon_photo_path' => '',
        'pin_user' => false,
        'pin_jewelry' => false,
        'error_message' => '',
        'last_activity' => time()
    ];
}

// Function to validate and sanitize POST data
function sanitize_post_data($post_data) {
    $sanitized = [];

    foreach ($post_data as $key => $value) {
        $sanitized[$key] = sanitize_string($value, 'POST_DATA', 1000);
    }

    return $sanitized;
}

// Function to validate uploaded files array
function validate_uploaded_files($files) {
    if (!is_array($files)) {
        return false;
    }

    return isset($files['user_photo']) && isset($files['jewelry_photo']) &&
           is_array($files['user_photo']) && is_array($files['jewelry_photo']);
}
?>
