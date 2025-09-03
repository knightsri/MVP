<?php
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

// Handle form submission
$user_photo_path = '';
$jewelry_photo_path = '';
$tryon_photo_path = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if files are uploaded
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

    $user_photo_path = $user_dest;
    $jewelry_photo_path = $jewelry_dest;

    // Prepare cURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Prepare multipart data with files and text prompt
    $postData = [
        'user_photo' => new CURLFile($user_dest, $user_photo['type'], $user_filename),
        'jewelry_photo' => new CURLFile($jewelry_dest, $jewelry_photo['type'], $jewelry_filename),
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

    $tryon_photo_path = $tryon_dest;
}

// Display the HTML page
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
            padding: 10px 30px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .section-title {
            margin-bottom: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Jewelry Try-On Application</h1>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <!-- Display results after submission -->
            <div class="top-section">
                <div class="image-box">
                    <div class="section-title">Your Photo</div>
                    <?php if ($user_photo_path): ?>
                        <img src="<?php echo htmlspecialchars($user_photo_path); ?>" alt="User Photo">
                    <?php endif; ?>
                </div>
                <div class="image-box">
                    <div class="section-title">Jewelry Photo</div>
                    <?php if ($jewelry_photo_path): ?>
                        <img src="<?php echo htmlspecialchars($jewelry_photo_path); ?>" alt="Jewelry Photo">
                    <?php endif; ?>
                </div>
            </div>
            <div class="bottom-section">
                <div class="section-title">Try-On Result</div>
                <?php if ($tryon_photo_path): ?>
                    <img src="<?php echo htmlspecialchars($tryon_photo_path); ?>" alt="Try-On Result">
                <?php else: ?>
                    <div class="processing">Processing... Please wait.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Display form initially -->
            <form action="" method="POST" enctype="multipart/form-data">
                <p>Upload your photo and the jewelry image to see the try-on result.</p>
                <label for="user_photo">Upload Your Photo:</label><br>
                <input type="file" id="user_photo" name="user_photo" accept="image/*" required><br><br>

                <label for="jewelry_photo">Upload Jewelry Photo:</label><br>
                <input type="file" id="jewelry_photo" name="jewelry_photo" accept="image/*" required><br><br>

                <button type="submit">Try It On</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
