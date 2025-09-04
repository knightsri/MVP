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
            $user_dest = isset($_POST['user_photo_path']) ? $_POST['user_photo_path'] : '';
            $jewelry_dest = isset($_POST['jewelry_photo_path']) ? $_POST['jewelry_photo_path'] : '';

            if (empty($user_dest) || empty($jewelry_dest) ||
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

// Display the HTML page based on current state
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jewelry Try-On Application</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            padding: 20px;
            margin: 0;
            background-color: #007bff;
            color: white;
        }
        .top-section {
            display: flex;
            justify-content: space-between;
            padding: 20px;
        }
        .image-box {
            flex: 1;
            margin: 0 10px;
            text-align: center;
            position: relative;
        }
        .image-box img {
            max-width: 100%;
            max-height: 300px;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        .bottom-section {
            padding: 20px;
            text-align: center;
        }
        .bottom-section img {
            max-width: 100%;
            max-height: 400px;
            border: 2px solid #007bff;
            border-radius: 5px;
        }
        .processing {
            font-size: 18px;
            color: #666;
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        form {
            padding: 40px;
            text-align: center;
        }
        input[type="file"] {
            display: block;
            margin: 10px auto;
            padding: 5px;
        }
        button {
            padding: 12px 30px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
        }
        button:hover {
            background-color: #0056b3;
        }

        input[type="hidden"] {
            display: none;
        }
        input[type="checkbox"] {
            margin: 5px;
        }
        .section-title {
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 18px;
        }
        .buttons-area {
            text-align: center;
            padding: 20px;
        }
        .success-message {
            color: #28a745;
            font-size: 18px;
            padding: 10px;
            text-align: center;
        }
        .pin-control {
            position: absolute;
            top: 5px;
            right: 5px;
            background: white;
            border: 1px solid #ccc;
            border-radius: 3px;
            padding: 3px 5px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Jewelry Try-On Application</h1>

        <?php if ($state === 'form'): ?>
            <!-- Initial upload form -->
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <p>Please upload the photos to begin.</p>
                <label for="user_photo">Upload Your Photo:</label><br>
                <input type="file" id="user_photo" name="user_photo" accept="image/*" required><br><br>

                <label for="jewelry_photo">Upload Jewelry Photo:</label><br>
                <input type="file" id="jewelry_photo" name="jewelry_photo" accept="image/*" required><br><br>

                <button type="submit">Upload Photos</button>
            </form>
        <?php elseif ($state === 'uploaded'): ?>
            <!-- Display uploaded photos and allow try-on -->
            <div class="top-section">
                <div class="image-box">
                    <div class="section-title">Your Photo</div>
                    <?php
                    if (file_exists($user_photo_path) && filesize($user_photo_path) > 0) {
                        $user_filename = basename($user_photo_path);
                        echo '<img src="?file=' . urlencode($user_filename) . '" alt="User Photo">';
                    } else {
                        echo '<div style="width: 100%; height: 300px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">Image not found</div>';
                    }
                    ?>
                    <div class="pin-control">
                        <label for="pin_user">
                            <input type="checkbox" id="pin_user" name="pin_user" <?php if ($pin_user) echo 'checked'; ?>> <b>PIN It!</b>
                        </label>
                    </div>
                </div>
                <div class="image-box">
                    <div class="section-title">Jewelry Photo</div>
                    <?php
                    if (file_exists($jewelry_photo_path) && filesize($jewelry_photo_path) > 0) {
                        $jewelry_filename = basename($jewelry_photo_path);
                        echo '<img src="?file=' . urlencode($jewelry_filename) . '" alt="Jewelry Photo">';
                    } else {
                        echo '<div style="width: 100%; height: 300px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">Image not found</div>';
                    }
                    ?>
                    <div class="pin-control">
                        <label for="pin_jewelry">
                            <input type="checkbox" id="pin_jewelry" name="pin_jewelry" <?php if ($pin_jewelry) echo 'checked'; ?>> <b>PIN It!</b>
                        </label>
                    </div>
                </div>
            </div>
            <div class="buttons-area">
                <form action="" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="tryon">
                    <input type="hidden" name="user_photo_path" value="<?php echo htmlspecialchars($user_photo_path); ?>">
                    <input type="hidden" name="jewelry_photo_path" value="<?php echo htmlspecialchars($jewelry_photo_path); ?>">
                    <input type="hidden" name="pin_user" value="<?php echo $pin_user ? 'on' : 'off'; ?>">
                    <input type="hidden" name="pin_jewelry" value="<?php echo $pin_jewelry ? 'on' : 'off'; ?>">
                    <button type="submit">Try On Jewelry</button>
                </form>
                <form action="" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="pin_user" value="<?php echo $pin_user ? 'on' : 'off'; ?>">
                    <input type="hidden" name="pin_jewelry" value="<?php echo $pin_jewelry ? 'on' : 'off'; ?>">
                    <button type="submit">Start Over</button>
                </form>
            </div>
        <?php elseif ($state === 'processed' && $tryon_photo_path): ?>
            <!-- Display all images and success message -->
            <div class="success-message">Jewelry try-on completed successfully!</div>
            <div class="top-section">
                <div class="image-box">
                    <div class="section-title">Your Photo</div>
                    <?php
                    if (file_exists($user_photo_path) && filesize($user_photo_path) > 0) {
                        $user_filename = basename($user_photo_path);
                        echo '<img src="?file=' . urlencode($user_filename) . '" alt="User Photo">';
                    } else {
                        echo '<div style="width: 100%; height: 300px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">Image not found</div>';
                    }
                    ?>
                    <div class="pin-control">
                        <label for="pin_user">
                            <input type="checkbox" id="pin_user" name="pin_user" <?php if ($pin_user) echo 'checked'; ?>> PIN
                        </label>
                    </div>
                </div>
                <div class="image-box">
                    <div class="section-title">Jewelry Photo</div>
                    <?php
                    if (file_exists($jewelry_photo_path) && filesize($jewelry_photo_path) > 0) {
                        $jewelry_filename = basename($jewelry_photo_path);
                        echo '<img src="?file=' . urlencode($jewelry_filename) . '" alt="Jewelry Photo">';
                    } else {
                        echo '<div style="width: 100%; height: 300px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">Image not found</div>';
                    }
                    ?>
                    <div class="pin-control">
                        <label for="pin_jewelry">
                            <input type="checkbox" id="pin_jewelry" name="pin_jewelry" <?php if ($pin_jewelry) echo 'checked'; ?>> PIN
                        </label>
                    </div>
                </div>
            </div>
            <div class="bottom-section">
                <div class="section-title">Try-On Result</div>
                <?php
                if (file_exists($tryon_photo_path) && filesize($tryon_photo_path) > 0) {
                    $tryon_filename = basename($tryon_photo_path);
                    echo '<img src="?file=' . urlencode($tryon_filename) . '" alt="Try-On Result">';
                } else {
                    echo '<div style="width: 100%; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">Try-on result not found</div>';
                }
                ?>
            </div>
            <div class="buttons-area">
                <form action="" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="pin_user" value="<?php echo $pin_user ? 'on' : 'off'; ?>">
                    <input type="hidden" name="pin_jewelry" value="<?php echo $pin_jewelry ? 'on' : 'off'; ?>">
                    <button type="submit">Start Over</button>
                </form>
            </div>

        <?php else: ?>
            <!-- Error state or unexpected condition -->
            <div style="text-align: center; padding: 40px;">
                <p><?php if(isset($error_message)) echo htmlspecialchars($error_message); else echo 'An error occurred. Please refresh the page and try again.'; ?></p>
                <div class="buttons-area">
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="reset">
                        <button type="submit">Start Over</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
