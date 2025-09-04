<?php
// Configuration variables
$webhook_url = 'https://your-n8n-webhook-url.com/endpoint'; // Replace with your n8n webhook URL
$content_type = 'image/png'; // Adjust to 'image/jpeg' if needed
$uploads_dir = 'uploads/';

// Security configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif'
]);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Ensure uploads directory exists with secure permissions
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
    // Create .htaccess to prevent direct access
    file_put_contents($uploads_dir . '.htaccess', "deny from all\n");
}
?>
