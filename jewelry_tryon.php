<?php
// Disable time limit for debugging purposes during troubleshooting
set_time_limit(0);

// Include configuration first to define constants, including debug settings
require_once 'config.php';

if (defined('DEBUG_ENABLED') && DEBUG_ENABLED) {
    // Force display of all errors for debugging
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Ensure errors are logged to the common error log file
    ini_set('log_errors', 1);
    ini_set('error_log', ERROR_LOG_FILE); // Use the common error log file
    error_log("jewelry_tryon.php: Script execution started. Debug logging enabled and directed to " . ERROR_LOG_FILE . " Current memory: " . memory_get_usage() . " Peak memory: " . memory_get_peak_usage(), E_USER_NOTICE); // General debug log for early issues
} else {
    // Disable display errors in production if DEBUG_ENABLED is false
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL); // Still report all errors, but don't display
    ini_set('log_errors', 1); // Ensure logging is still enabled
    ini_set('error_log', ERROR_LOG_FILE); // Direct to the common error log file
}

require_once 'functions.php';

try {
    error_log("jewelry_tryon.php: Starting request handling. Current memory: " . memory_get_usage() . " Peak memory: " . memory_get_peak_usage(), E_USER_NOTICE); // General debug log

    // Handle secure file serving with validation
    if (isset($_GET['file'])) {
        error_log("jewelry_tryon.php: GET request for file serving detected. Current memory: " . memory_get_usage(), E_USER_NOTICE);
        $file_param = sanitize_string($_GET['file'], 'FILE_SERVING', $config['validation']['max_filename_length']);

        if (!empty($file_param)) {
            log_error("Serving file: " . $file_param, 'FILE_SERVING', 'INFO');
            $file_path = $config['uploads']['directory'] . $file_param;
            $secure_path = serve_secure_file($file_path);

            if ($secure_path !== null && file_exists($secure_path)) {
                // Get MIME type for proper Content-Type header
                $mime_type = mime_content_type($secure_path);
                if (!$mime_type) {
                    $mime_type = 'application/octet-stream';
                }

                // Send appropriate headers
                header('Content-Type: ' . $mime_type);
                header('Content-Length: ' . filesize($secure_path));
                header('Cache-Control: private, max-age=' . $config['uploads']['cache_time']);
                header('X-Content-Type-Options: nosniff');

                // Output file content and exit
                readfile($secure_path);
                exit;
            } else {
                log_error("File not accessible: $file_param", 'FILE_SERVING', 'WARNING');

                header('HTTP/1.0 404 Not Found');
                header('Content-Type: text/plain');
                echo 'File not found or access denied.';
                exit;
            }
        }
    }

    // Initialize session state securely
    $session_state = initialize_session_state();

    // Initialize variables with validation
    $state = STATE_FORM;
    $user_photo_path = '';
    $jewelry_photo_path = '';
    $tryon_photo_path = '';
    $pin_user = false;
    $pin_jewelry = false;
    $error_message = '';

    // Handle form submissions with comprehensive validation
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("jewelry_tryon.php: POST request detected. Action: " . ($_POST['action'] ?? 'N/A'));
        log_error("jewelry_tryon.php: POST request initiated. Raw POST: " . print_r($_POST, true), 'FORM_PROCESSING', 'INFO');

        try {
            // Sanitize all POST data
            $postData = sanitize_post_data($_POST);
            log_error("jewelry_tryon.php: POST data sanitized. Sanitized: " . print_r($postData, true), 'FORM_PROCESSING', 'INFO');

            if (!isset($postData['action'])) {
                log_error("jewelry_tryon.php: Missing action parameter in POST data.", 'FORM_PROCESSING', 'ERROR');
                throw new Exception('Missing action parameter');
            }

            $action = validate_action($postData['action'], 'FORM_PROCESSING');
            log_error("jewelry_tryon.php: Action validated as: " . $action, 'FORM_PROCESSING', 'INFO');

            if (!$action) {
                throw new Exception('Invalid action parameter');
            }

            if ($action === ACTION_RESET) {
                // Handle reset action with validation
                $pin_user = validate_pin_state($postData['pin_user'] ?? '', 'RESET') === PIN_STATE_ON;
                $pin_jewelry = validate_pin_state($postData['pin_jewelry'] ?? '', 'RESET') === PIN_STATE_ON;

                // Reset to initial state, but respect pinned photos
                $state = STATE_FORM;
                if (!$pin_user) {
                    $user_photo_path = '';
                }
                if (!$pin_jewelry) {
                    $jewelry_photo_path = '';
                }
                $tryon_photo_path = '';

            } elseif ($action === ACTION_UPLOAD) {
                log_error("jewelry_tryon.php: Handling ACTION_UPLOAD. Raw FILES: " . print_r($_FILES, true), 'UPLOAD', 'INFO');
                // Handle photo upload with comprehensive validation
                if (!validate_uploaded_files($_FILES)) {
                     log_error("jewelry_tryon.php: validate_uploaded_files failed.", 'UPLOAD', 'ERROR');
                    throw new Exception('Both user photo and jewelry photo files are required');
                }

                $user_photo = $_FILES['user_photo'];
                $jewelry_photo = $_FILES['jewelry_photo'];

                log_error("jewelry_tryon.php: user_photo temp: " . ($user_photo['tmp_name'] ?? 'N/A') . ", jewelry_photo temp: " . ($jewelry_photo['tmp_name'] ?? 'N/A'), 'UPLOAD', 'INFO');

                // Validate user photo
                $user_validation = validate_file_upload($user_photo);
                if (!$user_validation['valid']) {
                    throw new Exception('User photo validation failed: ' . $user_validation['error']);
                }

                // Validate jewelry photo
                $jewelry_validation = validate_file_upload($jewelry_photo);
                if (!$jewelry_validation['valid']) {
                    throw new Exception('Jewelry photo validation failed: ' . $jewelry_validation['error']);
                }

                // Generate secure filenames
                $user_filename = generate_random_filename($user_photo['name']);
                $jewelry_filename = generate_random_filename($jewelry_photo['name']);

                // Construct safe file paths
                $user_dest = $config['uploads']['directory'] . $user_filename;
                $jewelry_dest = $config['uploads']['directory'] . $jewelry_filename;

                log_error("jewelry_tryon.php: Moving uploaded user photo from {$user_photo['tmp_name']} to {$user_dest}", 'UPLOAD', 'INFO');
                // Move uploaded files with error handling
                if (!move_uploaded_file($user_photo['tmp_name'], $user_dest)) {
                    log_error("jewelry_tryon.php: Failed to save user photo: {$user_photo['tmp_name']} to {$user_dest}", 'UPLOAD', 'ERROR');
                    throw new Exception('Failed to save user photo');
                }
                error_log("jewelry_tryon.php: User photo saved to: {$user_dest}. Current memory: " . memory_get_usage() . " Peak memory: " . memory_get_peak_usage(), E_USER_NOTICE);

                log_error("jewelry_tryon.php: Moving uploaded jewelry photo from {$jewelry_photo['tmp_name']} to {$jewelry_dest}", 'UPLOAD', 'INFO');
                if (!move_uploaded_file($jewelry_photo['tmp_name'], $jewelry_dest)) {
                    // Clean up the first file if second fails
                    log_error("jewelry_tryon.php: Failed to save jewelry photo: {$jewelry_photo['tmp_name']} to {$jewelry_dest}", 'UPLOAD', 'ERROR');
                    @unlink($user_dest);
                    throw new Exception('Failed to save jewelry photo');
                }
                 error_log("jewelry_tryon.php: Jewelry photo saved to: {$jewelry_dest}. Current memory: " . memory_get_usage() . " Peak memory: " . memory_get_peak_usage(), E_USER_NOTICE);

                // Set secure permissions
                @chmod($user_dest, $config['uploads']['file_permissions']);
                @chmod($jewelry_dest, $config['uploads']['file_permissions']);

                // Optimize uploaded images for better performance
                error_log("jewelry_tryon.php: Starting image optimization. Current memory: " . memory_get_usage() . " Peak memory: " . memory_get_peak_usage(), E_USER_NOTICE);
                $user_optimization = optimize_image($user_dest, $user_filename);
                $jewelry_optimization = optimize_image($jewelry_dest, $jewelry_filename);
                error_log("jewelry_tryon.php: Image optimization completed. Current memory: " . memory_get_usage() . " Peak memory: " . memory_get_peak_usage(), E_USER_NOTICE);

                if (!$user_optimization) {
                    log_error("User photo optimization failed but upload succeeded: $user_filename", 'UPLOAD', 'WARNING');
                }

                if (!$jewelry_optimization) {
                    log_error("Jewelry photo optimization failed but upload succeeded: $jewelry_filename", 'UPLOAD', 'WARNING');
                }

                // Get final image stats for logging
                $user_stats = get_image_stats($user_dest);
                $jewelry_stats = get_image_stats($jewelry_dest);

                if ($user_stats && $jewelry_stats) {
                    log_error("Optimized images - User: {$user_stats['width']}x{$user_stats['height']} ({$user_stats['size_human']}), Jewelry: {$jewelry_stats['width']}x{$jewelry_stats['height']} ({$jewelry_stats['size_human']})", 'UPLOAD', 'INFO');
                }

                // Update session state
                $state = STATE_UPLOADED;
                $user_photo_path = $user_dest;
                $jewelry_photo_path = $jewelry_dest;

                error_log("File upload and optimization successful: user=$user_filename, jewelry=$jewelry_filename. Current memory: " . memory_get_usage() . " Peak memory: " . memory_get_peak_usage(), E_USER_NOTICE);

            } elseif ($action === ACTION_TRYON) {
                error_log("jewelry_tryon.php: Handling ACTION_TRYON. Current memory: " . memory_get_usage() . " Peak memory: " . memory_get_peak_usage(), E_USER_NOTICE);
                // Handle try-on processing with validation
                $pin_user = validate_pin_state($postData['pin_user'] ?? '', 'TRYON') === PIN_STATE_ON;
                $pin_jewelry = validate_pin_state($postData['pin_jewelry'] ?? '', 'TRYON') === PIN_STATE_ON;

                // Sanitize and validate file paths from POST data
                $user_dest = sanitize_file_path(
                    $postData['user_photo_path'] ?? '',
                    $config['uploads']['directory']
                );
                $jewelry_dest = sanitize_file_path(
                    $postData['jewelry_photo_path'] ?? '',
                    $config['uploads']['directory']
                );
                log_error("jewelry_tryon.php: Try-on paths sanitized. User: {$user_dest}, Jewelry: {$jewelry_dest}", 'PROCESSING', 'INFO');

                // Check if paths are valid
                if ($user_dest === null || $jewelry_dest === null) {
                    throw new Exception('Invalid file path detected');
                }

                if (empty($user_dest) || empty($jewelry_dest) ||
                    !file_exists($user_dest) || !file_exists($jewelry_dest)) {
                    throw new Exception('Photo files not found. Please upload again');
                }

                log_error("jewelry_tryon.php: Calling webhook with user_dest: {$user_dest}, jewelry_dest: {$jewelry_dest}", 'PROCESSING', 'INFO');
                // Call webhook with retry mechanism
                $webhook_result = call_webhook_with_retry($user_dest, $jewelry_dest, $config['webhook']['max_retries']);

                if (!$webhook_result['success']) {
                    log_error("jewelry_tryon.php: Webhook call failed. Error: " . ($webhook_result['error'] ?? 'Unknown'), 'PROCESSING', 'ERROR');
                    throw new Exception('Webhook processing failed: ' . $webhook_result['error']);
                }
                log_error("jewelry_tryon.php: Webhook call successful. Response length: " . strlen($webhook_result['response']), 'PROCESSING', 'INFO');

                // Save the final try-on photo
                $tryon_filename = generate_random_filename('tryon_result.png');
                $tryon_dest = $config['uploads']['directory'] . $tryon_filename;

                if (file_put_contents($tryon_dest, $webhook_result['response']) === false) {
                    throw new Exception('Failed to save try-on result');
                }

                // Set permissions and update state
                @chmod($tryon_dest, $config['uploads']['file_permissions']);
                $state = STATE_PROCESSED;
                $tryon_photo_path = $tryon_dest;

                log_error("Try-on processing successful: $tryon_filename", 'PROCESSING', 'INFO');
            }

        } catch (Exception $e) {
            error_log("jewelry_tryon.php: Exception caught in POST handling: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            $error_message = handle_exception($e, 'FORM_PROCESSING');
            $state = STATE_FORM; // Reset to form on error
        }

    } else {
        // GET request - clear pin states for new session
        error_log("jewelry_tryon.php: GET request detected. Clearing pin states.");
        $pin_user = false;
        $pin_jewelry = false;
    }

} catch (Exception $e) {
    error_log("jewelry_tryon.php: Main exception caught: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    $error_message = handle_exception($e, 'MAIN_PROCESSING');
    $state = STATE_FORM;
}

error_log("jewelry_tryon.php: Script execution ending. Rendering template. Final memory: " . memory_get_usage() . " Peak memory: " . memory_get_peak_usage(), E_USER_NOTICE);
// Include the template to display the HTML
require_once 'template.php';
?>
