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

// Function to generate a random filename with original extension and optional prefix
function generate_random_filename($original_name, $prefix = '') {
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

    // Use prefix if provided, otherwise default to 'img_'
    $prefix = empty($prefix) ? 'img_' : sanitize_string($prefix, 'FILENAME_GEN');
    $filename = sprintf('%s%s_%s.%s', $prefix, date('Ymd_His', $timestamp), $random, $extension);

    return $filename;
}

// Function to get photos by prefix from uploads directory
function get_photos_by_prefix($prefix) {
    global $config;

    if (empty($prefix)) {
        log_error("Empty prefix provided to get_photos_by_prefix", 'PHOTO_LISTING', 'WARNING');
        return [];
    }

    $uploads_dir = $config['uploads']['directory'];
    if (!is_dir($uploads_dir)) {
        log_error("Uploads directory not found: $uploads_dir", 'PHOTO_LISTING', 'WARNING');
        return [];
    }

    $prefix = sanitize_string($prefix, 'PHOTO_LISTING');
    $photos = [];

    // Scan directory for files with the specified prefix
    $files = scandir($uploads_dir);
    if ($files === false) {
        log_error("Failed to scan uploads directory: $uploads_dir", 'PHOTO_LISTING', 'ERROR');
        return [];
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        // Check if file starts with the prefix and is a valid image file
        if (strpos($file, $prefix) === 0) {
            $file_path = $uploads_dir . $file;

            // Validate it's a readable file and an image
            if (is_file($file_path) && is_readable($file_path)) {
                $image_info = getimagesize($file_path);
                if ($image_info !== false && in_array($image_info['mime'], ALLOWED_MIME_TYPES)) {
                    $photos[] = $file;
                }
            }
        }
    }

    // Sort photos by modification time (newest first)
    usort($photos, function($a, $b) use ($uploads_dir) {
        return filemtime($uploads_dir . $b) - filemtime($uploads_dir . $a);
    });

    log_error("Found " . count($photos) . " photos with prefix '$prefix'", 'PHOTO_LISTING', 'INFO');
    return $photos;
}

// Function to get thumbnail data for photo display
function get_photo_thumbnail_data($photo_path) {
    global $config;

    if (empty($photo_path)) {
        log_error("Empty photo path provided to get_photo_thumbnail_data", 'THUMBNAIL_DATA', 'WARNING');
        return null;
    }

    $validated_path = validate_file_path($photo_path, 'THUMBNAIL_DATA');
    if (!$validated_path) {
        log_error("Invalid photo path: $photo_path", 'THUMBNAIL_DATA', 'WARNING');
        return null;
    }

    // Get basic image stats
    $stats = get_image_stats($validated_path);
    if (!$stats) {
        log_error("Failed to get image stats for: $photo_path", 'THUMBNAIL_DATA', 'WARNING');
        return null;
    }

    // Generate thumbnail dimensions (maintain aspect ratio)
    $thumbnail_max_width = 150;
    $thumbnail_max_height = 150;

    $aspect_ratio = $stats['width'] / $stats['height'];
    if ($aspect_ratio > 1) {
        // Landscape
        $thumb_width = $thumbnail_max_width;
        $thumb_height = $thumbnail_max_width / $aspect_ratio;
    } else {
        // Portrait or square
        $thumb_height = $thumbnail_max_height;
        $thumb_width = $thumbnail_max_height * $aspect_ratio;
    }

    // Calculate relative path from uploads directory for URL generation
    $uploads_dir = $config['uploads']['directory'];
    $relative_path = str_replace($uploads_dir, '', $validated_path);

    return [
        'filename' => basename($validated_path),
        'full_path' => $validated_path,
        'relative_path' => ltrim($relative_path, '/'),
        'width' => $stats['width'],
        'height' => $stats['height'],
        'mime_type' => $stats['mime_type'],
        'size_bytes' => $stats['size_bytes'],
        'size_human' => $stats['size_human'],
        'thumbnail_width' => (int) round($thumb_width),
        'thumbnail_height' => (int) round($thumb_height),
        'modification_time' => filemtime($validated_path),
        'url' => $config['uploads']['base_url'] . $relative_path
    ];
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
 * Image optimization functions
 */

// Function to optimize uploaded images
function optimize_image($image_path, $original_name = '') {
    global $config;

    if (!file_exists($image_path)) {
        log_error("Image optimization: File not found - $image_path", 'IMAGE_OPTIMIZATION', 'WARNING');
        return false;
    }

    // Get image information
    $image_info = getimagesize($image_path);
    if ($image_info === false) {
        log_error("Image optimization: Invalid image - $image_path", 'IMAGE_OPTIMIZATION', 'WARNING');
        return false;
    }

    $mime_type = $image_info['mime'];
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    $original_size = filesize($image_path);

    log_error("Image optimization: Starting - $original_name ($original_width x $original_height, {$original_size} bytes)", 'IMAGE_OPTIMIZATION', 'INFO');

    // Check if image needs optimization
    $needs_optimization = ($original_width > IMAGE_MAX_WIDTH || $original_height > IMAGE_MAX_HEIGHT);

    if (!$needs_optimization) {
        log_error("Image optimization: Image already optimal size - $original_name", 'IMAGE_OPTIMIZATION', 'INFO');
        return true;
    }

    // Backup original if configured
    if (IMAGE_BACKUP_ORIGINAL) {
        $backup_path = $image_path . '.original';
        if (!copy($image_path, $backup_path)) {
            log_error("Image optimization: Failed to create backup - $image_path", 'IMAGE_OPTIMIZATION', 'WARNING');
        } else {
            log_error("Image optimization: Backup created - $backup_path", 'IMAGE_OPTIMIZATION', 'INFO');
        }
    }

    $optimization_result = false;

    // Optimize based on image type
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $optimization_result = optimize_jpeg_image($image_path, $original_width, $original_height);
            break;
        case 'image/png':
            $optimization_result = optimize_png_image($image_path, $original_width, $original_height);
            break;
        case 'image/gif':
            $optimization_result = optimize_gif_image($image_path, $original_width, $original_height);
            break;
        default:
            log_error("Image optimization: Unsupported format - $mime_type", 'IMAGE_OPTIMIZATION', 'WARNING');
            return false;
    }

    if ($optimization_result) {
        $optimized_size = filesize($image_path);
        $size_saved = $original_size - $optimized_size;
        $size_saved_percent = round(($size_saved / $original_size) * 100, 1);

        log_error("Image optimization: Completed - $original_name (saved {$size_saved} bytes, {$size_saved_percent}%)", 'IMAGE_OPTIMIZATION', 'INFO');
    } else {
        log_error("Image optimization: Failed - $original_name", 'IMAGE_OPTIMIZATION', 'ERROR');
    }

    return $optimization_result;
}

// Function to optimize JPEG images
function optimize_jpeg_image($image_path, $original_width, $original_height) {
    // Calculate new dimensions
    list($new_width, $new_height) = calculate_optimal_dimensions($original_width, $original_height);

    try {
        // Create new image
        $source_image = imagecreatefromjpeg($image_path);
        if (!$source_image) {
            return false;
        }

        $optimized_image = imagecreatetruecolor($new_width, $new_height);

        // Enable transparency handling for JPEG
        $white = imagecolorallocate($optimized_image, 255, 255, 255);
        imagefill($optimized_image, 0, 0, $white);

        // Preserve EXIF orientation if available
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($image_path);
            if ($exif !== false && isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
                switch ($orientation) {
                    case 3:
                        $source_image = imagerotate($source_image, 180, 0);
                        break;
                    case 6:
                        $source_image = imagerotate($source_image, -90, 0);
                        break;
                    case 8:
                        $source_image = imagerotate($source_image, 90, 0);
                        break;
                }
            }
        }

        // Resize image
        imagecopyresampled($optimized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

        // Save optimized image
        $result = imagejpeg($optimized_image, $image_path, IMAGE_JPEG_QUALITY);

        // Clean up memory
        imagedestroy($source_image);
        imagedestroy($optimized_image);

        return $result;

    } catch (Exception $e) {
        log_error("JPEG optimization error: " . $e->getMessage(), 'IMAGE_OPTIMIZATION', 'ERROR');
        return false;
    }
}

// Function to optimize PNG images
function optimize_png_image($image_path, $original_width, $original_height) {
    // Calculate new dimensions
    list($new_width, $new_height) = calculate_optimal_dimensions($original_width, $original_height);

    try {
        // Create new image
        $source_image = imagecreatefrompng($image_path);
        if (!$source_image) {
            return false;
        }

        $optimized_image = imagecreatetruecolor($new_width, $new_height);

        // Enable alpha channel for PNG
        imagealphablending($optimized_image, false);
        imagesavealpha($optimized_image, true);

        // Resize image
        imagecopyresampled($optimized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

        // Save optimized image
        $result = imagepng($optimized_image, $image_path, IMAGE_PNG_COMPRESSION);

        // Clean up memory
        imagedestroy($source_image);
        imagedestroy($optimized_image);

        return $result;

    } catch (Exception $e) {
        log_error("PNG optimization error: " . $e->getMessage(), 'IMAGE_OPTIMIZATION', 'ERROR');
        return false;
    }
}

// Function to optimize GIF images
function optimize_gif_image($image_path, $original_width, $original_height) {
    // Calculate new dimensions
    list($new_width, $new_height) = calculate_optimal_dimensions($original_width, $original_height);

    try {
        // Create new image
        $source_image = imagecreatefromgif($image_path);
        if (!$source_image) {
            return false;
        }

        $optimized_image = imagecreatetruecolor($new_width, $new_height);

        // Handle transparency for GIF
        $transparent = imagecolorallocatealpha($optimized_image, 0, 0, 0, 127);
        imagealphablending($optimized_image, false);
        imagesavealpha($optimized_image, true);
        imagefill($optimized_image, 0, 0, $transparent);

        // Resize image
        imagecopyresampled($optimized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

        // Save optimized image
        $result = imagegif($optimized_image, $image_path);

        // Clean up memory
        imagedestroy($source_image);
        imagedestroy($optimized_image);

        return $result;

    } catch (Exception $e) {
        log_error("GIF optimization error: " . $e->getMessage(), 'IMAGE_OPTIMIZATION', 'ERROR');
        return false;
    }
}

// Function to calculate optimal dimensions (maintains aspect ratio)
function calculate_optimal_dimensions($original_width, $original_height) {
    $max_width = IMAGE_MAX_WIDTH;
    $max_height = IMAGE_MAX_HEIGHT;

    // If image is already within limits, return original dimensions
    if ($original_width <= $max_width && $original_height <= $max_height) {
        return [$original_width, $original_height];
    }

    // Calculate aspect ratio
    $aspect_ratio = $original_width / $original_height;

    // Calculate new dimensions
    if ($original_width > $original_height) {
        // Landscape or square
        $new_width = min($original_width, $max_width);
        $new_height = $new_width / $aspect_ratio;

        // If height exceeds limit, recalculate
        if ($new_height > $max_height) {
            $new_height = $max_height;
            $new_width = $new_height * $aspect_ratio;
        }
    } else {
        // Portrait
        $new_height = min($original_height, $max_height);
        $new_width = $new_height * $aspect_ratio;

        // If width exceeds limit, recalculate
        if ($new_width > $max_width) {
            $new_width = $max_width;
            $new_height = $new_width / $aspect_ratio;
        }
    }

    return [(int) round($new_width), (int) round($new_height)];
}

// Function to get image optimization statistics
function get_image_stats($image_path) {
    if (!file_exists($image_path)) {
        return null;
    }

    $image_info = getimagesize($image_path);
    if ($image_info === false) {
        return null;
    }

    return [
        'width' => $image_info[0],
        'height' => $image_info[1],
        'mime_type' => $image_info['mime'],
        'size_bytes' => filesize($image_path),
        'size_human' => format_bytes(filesize($image_path))
    ];
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
            log_error("Failed to initialize cURL handle.", 'WEBHOOK_CALL', 'ERROR');
            throw new Exception('Failed to initialize cURL');
        }

        $webhook_url = $config['webhook']['url'];
        log_error("Attempting webhook call to URL: " . $webhook_url, 'WEBHOOK_CALL', 'INFO');

        curl_setopt($ch, CURLOPT_URL, $webhook_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['webhook']['timeout']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        log_error("cURL options set. Timeout: " . $config['webhook']['timeout'] . "s", 'WEBHOOK_CALL', 'INFO');

        // Prepare multipart data with files and text prompt
        $user_filename = basename($user_path);
        $jewelry_filename = basename($jewelry_path);

        if (!file_exists($user_path) || !file_exists($jewelry_path)) {
            throw new Exception('Source files not found');
        }

        $mime_user = mime_content_type($user_path);
        if ($mime_user === false) {
             log_error("Failed to determine MIME type for user photo: $user_path. Falling back to application/octet-stream.", 'WEBHOOK_CALL', 'WARNING');
             $mime_user = 'application/octet-stream';
        }

        $mime_jewelry = mime_content_type($jewelry_path);
        if ($mime_jewelry === false) {
            log_error("Failed to determine MIME type for jewelry photo: $jewelry_path. Falling back to application/octet-stream.", 'WEBHOOK_CALL', 'WARNING');
            $mime_jewelry = 'application/octet-stream';
        }

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
            log_error("cURL_exec failed. Error No: {$curl_errno}, Error: {$curl_error}", 'WEBHOOK_CALL', 'ERROR');
            curl_close($ch);
            throw new Exception("cURL error [{$curl_errno}]: {$curl_error}");
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $request_info = curl_getinfo($ch); // Get all info for detailed logging
        curl_close($ch);

        log_error("cURL request completed. HTTP Code: {$http_code}. Request Info: " . print_r($request_info, true), 'WEBHOOK_CALL', 'INFO');
        log_error("cURL Response Body (first 500 chars): " . substr($response, 0, 500), 'WEBHOOK_CALL', 'INFO');

        if ($http_code !== 200) {
            log_error("Webhook returned non-200 HTTP status: {$http_code}", 'WEBHOOK_CALL', 'ERROR');
            throw new Exception("HTTP error: {$http_code}");
        }

        log_error("Webhook call successful (HTTP 200)", 'WEBHOOK', 'INFO');

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
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Initialize session variables if not set
    if (!isset($_SESSION['jewelry_app'])) {
        $_SESSION['jewelry_app'] = [
            'state' => STATE_FORM,
            'user_photo_path' => '',
            'jewelry_photo_path' => '',
            'tryon_photo_path' => '',
            'error_message' => '',
            'last_activity' => time()
        ];
    }

    return $_SESSION['jewelry_app'];
}

// Function to update session state
function update_session_state($key, $value) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['jewelry_app'])) {
        initialize_session_state();
    }

    $_SESSION['jewelry_app'][$key] = $value;
    $_SESSION['jewelry_app']['last_activity'] = time();
}

// Function to get session state value
function get_session_state($key, $default = null) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['jewelry_app'])) {
        initialize_session_state();
    }

    return $_SESSION['jewelry_app'][$key] ?? $default;
}

// Function to clear session state (for complete reset)
function clear_session_state() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    unset($_SESSION['jewelry_app']);
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

/**
 * Try-on result caching functions
 */

// Function to generate cache filename for try-on results
function generate_cache_filename($user_photo_path, $jewelry_photo_path) {
    global $config;

    // Extract base names without extensions
    $user_base = pathinfo($user_photo_path, PATHINFO_FILENAME);
    $jewelry_base = pathinfo($jewelry_photo_path, PATHINFO_FILENAME);

    // Format: userphoto-jewelryphoto.png
    $cache_filename = $user_base . '-' . $jewelry_base . '.png';

    return $config['uploads']['results_directory'] . $cache_filename;
}

// Function to check if cached result exists
function check_cached_result($user_photo_path, $jewelry_photo_path) {
    $cached_file_path = generate_cache_filename($user_photo_path, $jewelry_photo_path);

    if (file_exists($cached_file_path) && filesize($cached_file_path) > 0) {
        log_error("Cached result found: $cached_file_path", 'CACHE', 'INFO');
        return $cached_file_path;
    }

    log_error("No cached result found for combination", 'CACHE', 'INFO');
    return false;
}

// Function to save result to cache
function save_to_cache($user_photo_path, $jewelry_photo_path, $result_data) {
    global $config;

    $cached_file_path = generate_cache_filename($user_photo_path, $jewelry_photo_path);

    if (file_put_contents($cached_file_path, $result_data) === false) {
        log_error("Failed to save result to cache: $cached_file_path", 'CACHE', 'ERROR');
        return false;
    }

    @chmod($cached_file_path, $config['uploads']['file_permissions']);
    log_error("Result saved to cache: $cached_file_path", 'CACHE', 'INFO');
    return $cached_file_path;
}

/**
 * Photo gallery and thumbnail functions
 */

// Function to get user thumbnails (thumb_user_ prefix from thumbnails/ subfolder)
function get_user_thumbnails() {
    return get_thumbnails_from_subfolder('thumb_user_');
}

// Function to get jewelry thumbnails (thumb_jewel_ prefix from thumbnails/ subfolder)
function get_jewelry_thumbnails() {
    return get_thumbnails_from_subfolder('thumb_jewel_');
}

// Function to get thumbnails from thumbnails subfolder
function get_thumbnails_from_subfolder($prefix) {
    global $config;

    if (empty($prefix)) {
        log_error("Empty prefix provided to get_thumbnails_from_subfolder", 'THUMBNAIL_LISTING', 'WARNING');
        return [];
    }

    $thumbnails_dir = $config['uploads']['directory'] . 'thumbnails/';
    if (!is_dir($thumbnails_dir)) {
        log_error("Thumbnails directory not found: $thumbnails_dir", 'THUMBNAIL_LISTING', 'WARNING');
        return [];
    }

    $prefix = sanitize_string($prefix, 'THUMBNAIL_LISTING');
    $thumbnails = [];

    // Scan thumbnails directory for files with the specified prefix
    $files = scandir($thumbnails_dir);
    if ($files === false) {
        log_error("Failed to scan thumbnails directory: $thumbnails_dir", 'THUMBNAIL_LISTING', 'ERROR');
        return [];
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        // Check if file starts with the prefix and is a valid image file
        if (strpos($file, $prefix) === 0) {
            $file_path = $thumbnails_dir . $file;

            // Validate it's a readable file and an image
            if (is_file($file_path) && is_readable($file_path)) {
                $image_info = getimagesize($file_path);
                if ($image_info !== false && in_array($image_info['mime'], ALLOWED_MIME_TYPES)) {
                    $thumbnails[] = $file;
                }
            }
        }
    }

    // Sort thumbnails by modification time (newest first)
    usort($thumbnails, function($a, $b) use ($thumbnails_dir) {
        return filemtime($thumbnails_dir . $b) - filemtime($thumbnails_dir . $a);
    });

    log_error("Found " . count($thumbnails) . " thumbnails with prefix '$prefix'", 'THUMBNAIL_LISTING', 'INFO');
    return $thumbnails;
}

// Function to create thumbnail during upload
function create_upload_thumbnail($source_path, $original_filename, $prefix = 'img_') {
    global $config;

    if (!file_exists($source_path)) {
        log_error("Thumbnail creation: Source file not found - $source_path", 'THUMBNAIL_CREATION', 'WARNING');
        return false;
    }

    // Get image info
    $image_info = getimagesize($source_path);
    if ($image_info === false) {
        log_error("Thumbnail creation: Invalid image - $source_path", 'THUMBNAIL_CREATION', 'WARNING');
        return false;
    }

    $mime_type = $image_info['mime'];
    $original_width = $image_info[0];
    $original_height = $image_info[1];

    // Skip thumbnail creation if image is already small
    if ($original_width <= 150 && $original_height <= 150) {
        log_error("Thumbnail creation: Image already thumbnail size - $source_path", 'THUMBNAIL_CREATION', 'INFO');
        return true;
    }

    // Create thumbnail filename and save in thumbnails subfolder
    $thumbnail_filename = str_replace($prefix, 'thumb_' . $prefix, $original_filename);
    $thumbnail_dir = $config['uploads']['directory'] . 'thumbnails/';
    $thumbnail_path = $thumbnail_dir . $thumbnail_filename;

    // Ensure thumbnails directory exists
    if (!is_dir($thumbnail_dir)) {
        if (!mkdir($thumbnail_dir, 0755, true)) {
            log_error("Thumbnail creation: Failed to create thumbnails directory - $thumbnail_dir", 'THUMBNAIL_CREATION', 'ERROR');
            return false;
        }
    }

    try {
        // Create thumbnail image
        $thumbnail_result = false;

        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                $thumbnail_result = create_jpeg_thumbnail($source_path, $thumbnail_path, $original_width, $original_height);
                break;
            case 'image/png':
                $thumbnail_result = create_png_thumbnail($source_path, $thumbnail_path, $original_width, $original_height);
                break;
            case 'image/gif':
                $thumbnail_result = create_gif_thumbnail($source_path, $thumbnail_path, $original_width, $original_height);
                break;
            default:
                log_error("Thumbnail creation: Unsupported format - $mime_type", 'THUMBNAIL_CREATION', 'WARNING');
                return false;
        }

        if ($thumbnail_result && file_exists($thumbnail_path)) {
            @chmod($thumbnail_path, $config['uploads']['file_permissions']);
            log_error("Thumbnail created successfully: $thumbnail_filename", 'THUMBNAIL_CREATION', 'INFO');
            return true;
        } else {
            log_error("Thumbnail creation failed: $thumbnail_filename", 'THUMBNAIL_CREATION', 'ERROR');
            return false;
        }

    } catch (Exception $e) {
        log_error("Thumbnail creation exception: " . $e->getMessage(), 'THUMBNAIL_CREATION', 'ERROR');
        return false;
    }
}

// Function to create JPEG thumbnail
function create_jpeg_thumbnail($source_path, $thumbnail_path, $original_width, $original_height) {
    list($new_width, $new_height) = calculate_thumbnail_dimensions($original_width, $original_height, 150, 150);

    $source_image = imagecreatefromjpeg($source_path);
    if (!$source_image) {
        return false;
    }

    $thumbnail_image = imagecreatetruecolor($new_width, $new_height);

    // Fill with white background
    $white = imagecolorallocate($thumbnail_image, 255, 255, 255);
    imagefill($thumbnail_image, 0, 0, $white);

    // Resize
    imagecopyresampled($thumbnail_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

    $result = imagejpeg($thumbnail_image, $thumbnail_path, 85); // 85% quality for thumbnails

    imagedestroy($source_image);
    imagedestroy($thumbnail_image);

    return $result;
}

// Function to create PNG thumbnail
function create_png_thumbnail($source_path, $thumbnail_path, $original_width, $original_height) {
    list($new_width, $new_height) = calculate_thumbnail_dimensions($original_width, $original_height, 150, 150);

    $source_image = imagecreatefrompng($source_path);
    if (!$source_image) {
        return false;
    }

    $thumbnail_image = imagecreatetruecolor($new_width, $new_height);

    // Enable alpha channel
    imagealphablending($thumbnail_image, false);
    imagesavealpha($thumbnail_image, true);

    // Resize
    imagecopyresampled($thumbnail_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

    $result = imagepng($thumbnail_image, $thumbnail_path, 6); // Compression level 6

    imagedestroy($source_image);
    imagedestroy($thumbnail_image);

    return $result;
}

// Function to create GIF thumbnail
function create_gif_thumbnail($source_path, $thumbnail_path, $original_width, $original_height) {
    list($new_width, $new_height) = calculate_thumbnail_dimensions($original_width, $original_height, 150, 150);

    $source_image = imagecreatefromgif($source_path);
    if (!$source_image) {
        return false;
    }

    $thumbnail_image = imagecreatetruecolor($new_width, $new_height);

    // Enable transparency
    imagealphablending($thumbnail_image, false);
    imagesavealpha($thumbnail_image, true);

    // Resize
    imagecopyresampled($thumbnail_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

    $result = imagegif($thumbnail_image, $thumbnail_path);

    imagedestroy($source_image);
    imagedestroy($thumbnail_image);

    return $result;
}

// Function to calculate thumbnail dimensions (maintains aspect ratio)
function calculate_thumbnail_dimensions($original_width, $original_height, $max_width = 150, $max_height = 150) {
    // If image is already within limits, return original dimensions
    if ($original_width <= $max_width && $original_height <= $max_height) {
        return [$original_width, $original_height];
    }

    // Calculate aspect ratio
    $aspect_ratio = $original_width / $original_height;

    // Calculate new dimensions
    if ($original_width > $original_height) {
        // Landscape or square
        $new_width = min($original_width, $max_width);
        $new_height = $new_width / $aspect_ratio;

        // If height exceeds limit, recalculate
        if ($new_height > $max_height) {
            $new_height = $max_height;
            $new_width = $new_height * $aspect_ratio;
        }
    } else {
        // Portrait
        $new_height = min($original_height, $max_height);
        $new_width = $new_height * $aspect_ratio;

        // If width exceeds limit, recalculate
        if ($new_width > $max_width) {
            $new_width = $max_width;
            $new_height = $new_width / $aspect_ratio;
        }
    }

    return [(int) round($new_width), (int) round($new_height)];
}


// Helper function for memory logging
if (!function_exists('format_bytes')) {
    function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
    $pow = (int) floor(($bytes ? log($bytes) : 0) / 1024);
    $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
?>
