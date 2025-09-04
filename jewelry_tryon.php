<?php
// Include configuration and functions
require_once 'config.php';
require_once 'functions.php';

// Handle secure file serving
if (isset($_GET['file']) && !empty($_GET['file'])) {
    $file_path = $uploads_dir . basename($_GET['file']); // Use basename to prevent directory traversal
    $secure_path = serve_secure_file($file_path);

    if ($secure_path !== null) {
        // Get MIME type for proper Content-Type header
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $secure_path);
        finfo_close($finfo);

        // Send appropriate headers
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($secure_path));
        header('Cache-Control: private, max-age=3600'); // Cache for 1 hour

        // Output file content
        readfile($secure_path);
        exit;
    } else {
        // File not found or invalid path
        header('HTTP/1.0 404 Not Found');
        exit;
    }
}

// Initialize variables
$state = 'form';
$user_photo_path = isset($_POST['user_photo_path']) ? $_POST['user_photo_path'] : '';
$jewelry_photo_path = isset($_POST['jewelry_photo_path']) ? $_POST['jewelry_photo_path'] : '';
$tryon_photo_path = '';
$pin_user = isset($_POST['pin_user']) && $_POST['pin_user'] === 'on';
$pin_jewelry = isset($_POST['pin_jewelry']) && $_POST['pin_jewelry'] === 'on';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'reset') {
            // Reset to initial state, but respect pinned photos
            $state = 'form';
            if (!$pin_user) {
                $user_photo_path = '';
            }
            if (!$pin_jewelry) {
                $jewelry_photo_path = '';
            }
            $tryon_photo_path = '';
        } elseif ($_POST['action'] === 'upload') {
            // Handle photo upload stage
            if (!isset($_FILES['user_photo']) || !isset($_FILES['jewelry_photo'])) {
                $error_message = 'Both user photo and jewelry photo are required.';
            } else {
                $user_photo = $_FILES['user_photo'];
                $jewelry_photo = $_FILES['jewelry_photo'];

                // Validate user photo
                $user_validation = validate_file_upload($user_photo);
                if (!$user_validation['valid']) {
                    $error_message = 'User photo: ' . $user_validation['error'];
                } else {
                    // Validate jewelry photo
                    $jewelry_validation = validate_file_upload($jewelry_photo);
                    if (!$jewelry_validation['valid']) {
                        $error_message = 'Jewelry photo: ' . $jewelry_validation['error'];
                    } else {
                        // Generate random filenames
                        $user_filename = generate_random_filename($user_photo['name']);
                        $jewelry_filename = generate_random_filename($jewelry_photo['name']);

                        // Move uploaded files to uploads directory
                        $user_dest = $uploads_dir . $user_filename;
                        $jewelry_dest = $uploads_dir . $jewelry_filename;

                        if (move_uploaded_file($user_photo['tmp_name'], $user_dest) &&
                            move_uploaded_file($jewelry_photo['tmp_name'], $jewelry_dest)) {
                            // Set secure file permissions
                            chmod($user_dest, 0644);
                            chmod($jewelry_dest, 0644);

                            $state = 'uploaded';
                            $user_photo_path = $user_dest;
                            $jewelry_photo_path = $jewelry_dest;
                        } else {
                            $error_message = 'Failed to save uploaded files.';
                        }
                    }
                }
            }
        } elseif ($_POST['action'] === 'tryon') {
            // Handle try-on processing stage

            // Sanitize and validate file paths from POST data
            $user_dest = sanitize_file_path(isset($_POST['user_photo_path']) ? $_POST['user_photo_path'] : '', $uploads_dir);
            $jewelry_dest = sanitize_file_path(isset($_POST['jewelry_photo_path']) ? $_POST['jewelry_photo_path'] : '', $uploads_dir);

            // Check if paths are valid
            if ($user_dest === null || $jewelry_dest === null) {
                $error_message = 'Invalid file path detected.';
                $state = 'form';
            } elseif (empty($user_dest) || empty($jewelry_dest) ||
                !file_exists($user_dest) || !file_exists($jewelry_dest)) {
                $error_message = 'Photos not found. Please upload again.';
            } else {
                // Prepare cURL request
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $webhook_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                // Prepare multipart data with files and text prompt
                $user_filename = basename($user_dest);
                $jewelry_filename = basename($jewelry_dest);

                $postData = [
                    'user_photo' => new CURLFile($user_dest, mime_content_type($user_dest), $user_filename),
                    'jewelry_photo' => new CURLFile($jewelry_dest, mime_content_type($jewelry_dest), $jewelry_filename),
                    'prompt' => 'Try on this jewelry bracelet on the user\'s wrist', // Text prompt for AI
                ];

                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

                // Execute the request
                $response = curl_exec($ch);
                $error = curl_error($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($error || $http_code !== 200) {
                    $error_message = 'Failed to process the request. HTTP Code: ' . $http_code . ', Error: ' . $error;
                } else {
                    // Save the final try-on photo
                    $tryon_filename = generate_random_filename('tryon_result.png');
                    $tryon_dest = $uploads_dir . $tryon_filename;
                    if (file_put_contents($tryon_dest, $response) !== false) {
                        $state = 'processed';
                        $tryon_photo_path = $tryon_dest;
                    } else {
                        $error_message = 'Failed to save the try-on result.';
                    }
                }
            }
        }
    }
} else {
    // If GET request, clear pin states for new session
    $pin_user = false;
    $pin_jewelry = false;
}

// Include the template to display the HTML
require_once 'template.php';
?>
