<?php
// Start session to manage application state
session_start();

// Configuration variables
$webhook_url = 'https://your-n8n-webhook-url.com/endpoint'; // Replace with your n8n webhook URL
$content_type = 'image/png'; // Adjust to 'image/jpeg' if needed
$uploads_dir = 'uploads/';

// Ensure uploads directory exists
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

// Function to generate a random filename with original extension
function generate_random_filename($original_name) {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    return uniqid('img_', true) . '.' . $extension;
}

// Initialize session variables if not set
if (!isset($_SESSION['state'])) {
    $_SESSION['state'] = 'form';
    $_SESSION['user_photo_path'] = '';
    $_SESSION['jewelry_photo_path'] = '';
    $_SESSION['tryon_photo_path'] = '';
}

// Determine current state
$state = $_SESSION['state'];
$user_photo_path = $_SESSION['user_photo_path'];
$jewelry_photo_path = $_SESSION['jewelry_photo_path'];
$tryon_photo_path = $_SESSION['tryon_photo_path'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'upload') {
            // Handle photo upload stage
            if (!isset($_FILES['user_photo']) || !isset($_FILES['jewelry_photo'])) {
                die('<p>Error: Both user photo and jewelry photo are required.</p>');
            }

            $user_photo = $_FILES['user_photo'];
            $jewelry_photo = $_FILES['jewelry_photo'];

            // Validate uploads
            if ($user_photo['error'] !== UPLOAD_ERR_OK || $jewelry_photo['error'] !== UPLOAD_ERR_OK) {
                die('<p>Error: File upload failed.</p>');
            }

            // Generate random filenames
            $user_filename = generate_random_filename($user_photo['name']);
            $jewelry_filename = generate_random_filename($jewelry_photo['name']);

            // Move uploaded files to uploads directory
            $user_dest = $uploads_dir . $user_filename;
            $jewelry_dest = $uploads_dir . $jewelry_filename;

            if (!move_uploaded_file($user_photo['tmp_name'], $user_dest) ||
                !move_uploaded_file($jewelry_photo['tmp_name'], $jewelry_dest)) {
                die('<p>Error: Failed to save uploaded files.</p>');
            }

            // Update session variables
            $_SESSION['state'] = 'uploaded';
            $_SESSION['user_photo_path'] = $user_dest;
            $_SESSION['jewelry_photo_path'] = $jewelry_dest;

            // Update local variables
            $state = 'uploaded';
            $user_photo_path = $user_dest;
            $jewelry_photo_path = $jewelry_dest;
        } elseif ($_POST['action'] === 'tryon') {
            // Handle try-on processing stage
            if (empty($_SESSION['user_photo_path']) || empty($_SESSION['jewelry_photo_path'])) {
                die('<p>Error: Photos not found. Please upload again.</p>');
            }

            $user_dest = $_SESSION['user_photo_path'];
            $jewelry_dest = $_SESSION['jewelry_photo_path'];

            // Prepare cURL request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $webhook_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Prepare multipart data with files and text prompt
            $user_filename = basename($user_dest);
            $jewelry_filename = basename($jewelry_dest);
            $user_info = pathinfo($user_dest);
            $jewelry_info = pathinfo($jewelry_dest);

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
                die('<p>Error: Failed to process the request. HTTP Code: ' . $http_code . ', Error: ' . $error . '</p>');
            }

            // Save the final try-on photo
            $tryon_filename = generate_random_filename('tryon_result.png');
            $tryon_dest = $uploads_dir . $tryon_filename;
            if (file_put_contents($tryon_dest, $response) === false) {
                die('<p>Error: Failed to save the try-on result.</p>');
            }

            // Update session variables
            $_SESSION['state'] = 'processed';
            $_SESSION['tryon_photo_path'] = $tryon_dest;

            // Update local variables
            $state = 'processed';
            $tryon_photo_path = $tryon_dest;
        }
    }
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
                    <img src="<?php echo htmlspecialchars($user_photo_path); ?>" alt="User Photo">
                </div>
                <div class="image-box">
                    <div class="section-title">Jewelry Photo</div>
                    <img src="<?php echo htmlspecialchars($jewelry_photo_path); ?>" alt="Jewelry Photo">
                </div>
            </div>
            <div class="buttons-area">
                <form action="" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="tryon">
                    <button type="submit">Try On Jewelry</button>
                </form>
            </div>
        <?php elseif ($state === 'processed' && $tryon_photo_path): ?>
            <!-- Display all images and success message -->
            <div class="success-message">Jewelry try-on completed successfully!</div>
            <div class="top-section">
                <div class="image-box">
                    <div class="section-title">Your Photo</div>
                    <img src="<?php echo htmlspecialchars($user_photo_path); ?>" alt="User Photo">
                </div>
                <div class="image-box">
                    <div class="section-title">Jewelry Photo</div>
                    <img src="<?php echo htmlspecialchars($jewelry_photo_path); ?>" alt="Jewelry Photo">
                </div>
            </div>
            <div class="bottom-section">
                <div class="section-title">Try-On Result</div>
                <img src="<?php echo htmlspecialchars($tryon_photo_path); ?>" alt="Try-On Result">
            </div>
        <?php else: ?>
            <!-- Error state or unexpected condition -->
            <div style="text-align: center; padding: 40px;">
                <p>An error occurred. Please refresh the page and try again.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
