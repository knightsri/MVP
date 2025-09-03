<?php
// $webhook_url should be replaced with your n8n webhook URL
$webhook_url = 'https://your-n8n-webhook-url.com/endpoint';

// The expected output Content-Type from the API
$content_type = 'image/png'; // Adjust to 'image/jpeg' if needed

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if files are uploaded
    if (!isset($_FILES['user_photo']) || !isset($_FILES['jewelry_photo'])) {
        die('<p>Error: Both user photo and jewelry photo are required.</p>');
    }

    $user_photo_tmp = $_FILES['user_photo']['tmp_name'];
    $jewelry_photo_tmp = $_FILES['jewelry_photo']['tmp_name'];

    // Ensure temporary files exist
    if (!file_exists($user_photo_tmp) || !file_exists($jewelry_photo_tmp)) {
        die('<p>Error: Invalid uploaded files.</p>');
    }

    // Prepare cURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Prepare multipart data with CURLFile
    $postData = [
        'user_photo' => new CURLFile($user_photo_tmp, $_FILES['user_photo']['type'], $_FILES['user_photo']['name']),
        'jewelry_photo' => new CURLFile($jewelry_photo_tmp, $_FILES['jewelry_photo']['type'], $_FILES['jewelry_photo']['name']),
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

    // Set Content-Type and output the binary image data
    header('Content-Type: ' . $content_type);
    echo $response;

    // Clean up temporary files after processing
    if (file_exists($user_photo_tmp)) {
        unlink($user_photo_tmp);
    }
    if (file_exists($jewelry_photo_tmp)) {
        unlink($jewelry_photo_tmp);
    }

    exit;
} else {
    // Display the form when not submitted
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
            margin: 20px;
            text-align: center;
        }
        form {
            max-width: 400px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        input[type="file"] {
            margin: 10px 0;
        }
        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .processing {
            display: none;
            margin-top: 20px;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <h1>Jewelry Try-On Application</h1>
    <p>Upload your photo and the jewelry image to see how it looks on you.</p>
    <form action="" method="POST" enctype="multipart/form-data" id="tryon-form">
        <label for="user_photo">Upload Your Photo:</label><br>
        <input type="file" id="user_photo" name="user_photo" accept="image/*" required><br><br>

        <label for="jewelry_photo">Upload Jewelry Photo:</label><br>
        <input type="file" id="jewelry_photo" name="jewelry_photo" accept="image/*" required><br><br>

        <button type="submit">Try It On</button>
    </form>

    <div class="processing" id="processing">
        <div class="spinner"></div>
        <p>Processing... Please wait.</p>
    </div>

    <script>
        const form = document.getElementById('tryon-form');
        const processing = document.getElementById('processing');

        form.addEventListener('submit', function() {
            form.style.display = 'none';
            processing.style.display = 'block';
        });
    </script>
</body>
</html>
<?php
}
?>
