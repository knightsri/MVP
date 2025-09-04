<?php
// Function to generate a random filename with original extension
function generate_random_filename($original_name) {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    return uniqid('img_', true) . '.' . $extension;
}

// Function to validate uploaded files
function validate_file_upload($file) {
    // Check if file upload was successful
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload failed.'];
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['valid' => false, 'error' => 'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.'];
    }

    // Validate file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['valid' => false, 'error' => 'Invalid file type. Only ' . implode(', ', ALLOWED_EXTENSIONS) . ' files are allowed.'];
    }

    // Get real MIME type from file content (more secure than relying on browser)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Validate MIME type
    if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
        return ['valid' => false, 'error' => 'Invalid file format detected.'];
    }

    // Additional check: verify file is actually an image
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['valid' => false, 'error' => 'File is not a valid image.'];
    }

    return ['valid' => true, 'error' => null];
}

// Function to serve files securely
function serve_secure_file($file_path) {
    // Validate file path to prevent directory traversal
    $real_path = realpath($file_path);
    $uploads_real_path = realpath(__DIR__ . '/uploads/');

    // Check if file is within uploads directory
    if (!$real_path || strpos($real_path, $uploads_real_path) !== 0) {
        return null;
    }

    if (!file_exists($real_path)) {
        return null;
    }

    return $real_path;
}

// Function to sanitize and validate user-provided file paths
function sanitize_file_path($posted_path, $uploads_dir) {
    // Remove any null bytes or dangerous characters
    $clean_path = str_replace(array("\0", "\r", "\n"), '', $posted_path);

    // Get the filename (basename) to prevent directory traversal
    $filename = basename($clean_path);

    // Validate that filename doesn't contain dangerous sequences
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return null; // Invalid path
    }

    // Construct safe path within uploads directory
    $safe_path = $uploads_dir . $filename;

    // Validate the real path is within uploads directory
    $real_path = realpath($safe_path);
    $uploads_real_path = realpath(__DIR__ . '/' . dirname($uploads_dir));

    if (!$real_path || strpos($real_path, $uploads_real_path) !== 0) {
        return null;
    }

    return $safe_path;
}
?>
