<?php
// Variables from the main script should be available here
// $state, $user_photo_path, $jewelry_photo_path, $tryon_photo_path, $error_message
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
            <!-- Upload form -->
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="user_photo_selected" id="user_photo_selected" value="">
                <input type="hidden" name="jewelry_photo_selected" id="jewelry_photo_selected" value="">

                <table class="upload-table">
                    <tr>
                        <td class="upload-column">
                            <div class="upload-section">
                                <h3>Upload Your Photo</h3>
                                <label for="user_photo">Choose file:</label><br>
                                <input type="file" id="user_photo" name="user_photo" accept="image/*"><br><br>

                                <!-- User photos thumbnail gallery -->
                                <div class="thumbnail-gallery-container">
                                    <h4>Or select from gallery:</h4>
                                    <div class="thumbnail-gallery" id="user-gallery">
                                        <?php
                                        $user_thumbnails = get_user_thumbnails();
                                        foreach ($user_thumbnails as $thumbnail) {
                                            // Convert thumbnail filename back to original filename
                                            $original_filename = str_replace('thumb_', '', $thumbnail);
                                            $thumbnail_url = urlencode('thumbnails/' . $thumbnail);
                                            echo '<img src="?file=' . $thumbnail_url . '" alt="User thumbnail" class="thumbnail" onclick="selectUserThumbnail(\'' . $original_filename . '\')">';
                                        }
                                        if (empty($user_thumbnails)) {
                                            echo '<p class="no-thumbnails">No user photos uploaded yet.</p>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="upload-column">
                            <div class="upload-section">
                                <h3>Upload Jewelry Photo</h3>
                                <label for="jewelry_photo">Choose file:</label><br>
                                <input type="file" id="jewelry_photo" name="jewelry_photo" accept="image/*"><br><br>

                                <!-- Jewelry photos thumbnail gallery -->
                                <div class="thumbnail-gallery-container">
                                    <h4>Or select from gallery:</h4>
                                    <div class="thumbnail-gallery" id="jewelry-gallery">
                                        <?php
                                        $jewelry_thumbnails = get_jewelry_thumbnails();
                                        foreach ($jewelry_thumbnails as $thumbnail) {
                                            // Convert thumbnail filename back to original filename
                                            $original_filename = str_replace('thumb_', '', $thumbnail);
                                            $thumbnail_url = urlencode('thumbnails/' . $thumbnail);
                                            echo '<img src="?file=' . $thumbnail_url . '" alt="Jewelry thumbnail" class="thumbnail" onclick="selectJewelryThumbnail(\'' . $original_filename . '\')">';
                                        }
                                        if (empty($jewelry_thumbnails)) {
                                            echo '<p class="no-thumbnails">No jewelry photos uploaded yet.</p>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="submit-section">
                    <button type="submit" onclick="return validateSubmission()">Upload/Select Photos</button>
                </div>
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
                </div>
            </div>
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <div class="buttons-area">
                <form action="" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="tryon">
                    <input type="hidden" name="user_photo_path" value="<?php echo htmlspecialchars($user_photo_path); ?>">
                    <input type="hidden" name="jewelry_photo_path" value="<?php echo htmlspecialchars($jewelry_photo_path); ?>">
                    <button type="submit" <?php if (!empty($error_message)) echo 'disabled'; ?>>Try On Jewelry</button>
                </form>
                <form action="" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="reset">
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
                    if (!empty($user_photo_path) && file_exists($user_photo_path) && filesize($user_photo_path) > 0) {
                        $user_filename = basename($user_photo_path);
                        echo '<img src="?file=' . urlencode($user_filename) . '" alt="User Photo">';
                    } else {
                        echo '<div style="width: 100%; height: 300px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">No user photo available</div>';
                    }
                    ?>
                </div>
                <div class="image-box">
                    <div class="section-title">Jewelry Photo</div>
                    <?php
                    if (!empty($jewelry_photo_path) && file_exists($jewelry_photo_path) && filesize($jewelry_photo_path) > 0) {
                        $jewelry_filename = basename($jewelry_photo_path);
                        echo '<img src="?file=' . urlencode($jewelry_filename) . '" alt="Jewelry Photo">';
                    } else {
                        echo '<div style="width: 100%; height: 300px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">No jewelry photo available</div>';
                    }
                    ?>
                </div>
            </div>
            <div class="bottom-section">
                <div class="section-title">Try-On Result</div>
                <?php
                if (!empty($tryon_photo_path) && file_exists($tryon_photo_path) && filesize($tryon_photo_path) > 0) {
                    $tryon_filename = basename($tryon_photo_path);
                    // If the try-on result is in the cache (uploads/results/), use 'results/<filename>'
                    $tryon_relative_path = '';
                    if (strpos($tryon_photo_path, DIRECTORY_SEPARATOR . 'results' . DIRECTORY_SEPARATOR) !== false) {
                        $tryon_relative_path = 'results/' . $tryon_filename;
                    } else {
                        $tryon_relative_path = $tryon_filename;
                    }
                    echo '<img src="?file=' . urlencode($tryon_relative_path) . '" alt="Try-On Result">';
                } else {
                    echo '<div style="width: 100%; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">Try-on result not available. Please try again.</div>';
                }
                ?>
            </div>
            <div class="buttons-area">
                <form action="" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="reset">
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

    <script>
        function selectUserThumbnail(filename) {
            // Remove previous selection
            const gallery = document.getElementById('user-gallery');
            const thumbnails = gallery.querySelectorAll('.thumbnail');
            thumbnails.forEach(thumb => thumb.classList.remove('selected'));

            // Add selection to clicked thumbnail
            event.target.classList.add('selected');

            // Update hidden field
            document.getElementById('user_photo_selected').value = filename;

            // Clear file input
            document.getElementById('user_photo').value = '';
        }

        function selectJewelryThumbnail(filename) {
            // Remove previous selection
            const gallery = document.getElementById('jewelry-gallery');
            const thumbnails = gallery.querySelectorAll('.thumbnail');
            thumbnails.forEach(thumb => thumb.classList.remove('selected'));

            // Add selection to clicked thumbnail
            event.target.classList.add('selected');

            // Update hidden field
            document.getElementById('jewelry_photo_selected').value = filename;

            // Clear file input
            document.getElementById('jewelry_photo').value = '';
        }

        // Clear thumbnail selection when file input changes
        // Prevent double submission for Try On Jewelry
        document.addEventListener('DOMContentLoaded', function() {
            var tryonBtn = document.getElementById('tryonBtn');
            if (tryonBtn) {
                tryonBtn.addEventListener('click', function(e) {
                    tryonBtn.disabled = true;
                    tryonBtn.textContent = 'Processing...';
                });
            }
        });
        document.getElementById('user_photo').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.getElementById('user_photo_selected').value = '';
                const gallery = document.getElementById('user-gallery');
                const thumbnails = gallery.querySelectorAll('.thumbnail');
                thumbnails.forEach(thumb => thumb.classList.remove('selected'));
            }
        });

        document.getElementById('jewelry_photo').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.getElementById('jewelry_photo_selected').value = '';
                const gallery = document.getElementById('jewelry-gallery');
                const thumbnails = gallery.querySelectorAll('.thumbnail');
                thumbnails.forEach(thumb => thumb.classList.remove('selected'));
            }
        });

        function validateSubmission() {
            const userPhotoInput = document.getElementById('user_photo');
            const jewelryPhotoInput = document.getElementById('jewelry_photo');
            const userSelected = document.getElementById('user_photo_selected').value;
            const jewelrySelected = document.getElementById('jewelry_photo_selected').value;

            // Check if user has either uploaded a file or selected from gallery for each item
            const hasUserPhoto = userPhotoInput.files.length > 0 || userSelected !== '';
            const hasJewelryPhoto = jewelryPhotoInput.files.length > 0 || jewelrySelected !== '';

            if (!hasUserPhoto || !hasJewelryPhoto) {
                let missingItems = [];
                if (!hasUserPhoto) {
                    missingItems.push('user photo');
                }
                if (!hasJewelryPhoto) {
                    missingItems.push('jewelry photo');
                }

                alert('Please either upload files or select from gallery for: ' + missingItems.join(' and '));
                return false; // Prevent form submission
            }

            return true; // Allow form submission
        }
    </script>
</body>
</html>
