<?php
// Variables from the main script should be available here
// $state, $user_photo_path, $jewelry_photo_path, $tryon_photo_path, $pin_user, $pin_jewelry, $error_message
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jewelry Try-On Application</title>
    <link rel="stylesheet" href="styles.css">
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
