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
    <script>
        // JavaScript for PIN checkbox functionality
        function updatePinState(photoType, isChecked) {
            // Update hidden form fields for try-on and reset forms
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                const hiddenField = form.querySelector(`input[name="pin_${photoType}"]`);
                if (hiddenField) {
                    hiddenField.value = isChecked ? 'on' : 'off';
                }
            });
            
            // Visual feedback
            const checkbox = document.getElementById(`pin_${photoType}`);
            if (checkbox) {
                checkbox.checked = isChecked;
                
                // Add visual indication that PIN state changed
                const imageBox = checkbox.closest('.image-box');
                if (imageBox) {
                    if (isChecked) {
                        imageBox.classList.add('pinned');
                        // Show pin indicator
                        let pinIndicator = imageBox.querySelector('.pin-indicator');
                        if (!pinIndicator) {
                            pinIndicator = document.createElement('div');
                            pinIndicator.className = 'pin-indicator';
                            pinIndicator.innerHTML = '📌 PINNED';
                            imageBox.appendChild(pinIndicator);
                        }
                    } else {
                        imageBox.classList.remove('pinned');
                        // Remove pin indicator
                        const pinIndicator = imageBox.querySelector('.pin-indicator');
                        if (pinIndicator) {
                            pinIndicator.remove();
                        }
                    }
                }
            }
        }

        // Initialize PIN functionality when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Set up event listeners for PIN checkboxes
            const userPinCheckbox = document.getElementById('pin_user');
            const jewelryPinCheckbox = document.getElementById('pin_jewelry');
            
            if (userPinCheckbox) {
                userPinCheckbox.addEventListener('change', function() {
                    updatePinState('user', this.checked);
                });
                
                // Initialize visual state
                updatePinState('user', userPinCheckbox.checked);
            }
            
            if (jewelryPinCheckbox) {
                jewelryPinCheckbox.addEventListener('change', function() {
                    updatePinState('jewelry', this.checked);
                });
                
                // Initialize visual state
                updatePinState('jewelry', jewelryPinCheckbox.checked);
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Jewelry Try-On Application</h1>

        <?php if ($state === 'form'): ?>
            <!-- Upload form with PIN state support -->
            <?php if ($pin_user || $pin_jewelry): ?>
                <div class="pinned-photos-section">
                    <p><strong>Pinned Photos:</strong></p>
                    <?php if ($pin_user && !empty($user_photo_path) && file_exists($user_photo_path)): ?>
                        <div class="image-box pinned-image">
                            <div class="section-title">📌 Pinned User Photo</div>
                            <?php
                            $user_filename = basename($user_photo_path);
                            echo '<img src="?file=' . urlencode($user_filename) . '" alt="Pinned User Photo">';
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($pin_jewelry && !empty($jewelry_photo_path) && file_exists($jewelry_photo_path)): ?>
                        <div class="image-box pinned-image">
                            <div class="section-title">📌 Pinned Jewelry Photo</div>
                            <?php
                            $jewelry_filename = basename($jewelry_photo_path);
                            echo '<img src="?file=' . urlencode($jewelry_filename) . '" alt="Pinned Jewelry Photo">';
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                
                <?php if (!$pin_user): ?>
                    <label for="user_photo">Upload Your Photo:</label><br>
                    <input type="file" id="user_photo" name="user_photo" accept="image/*" required><br><br>
                <?php else: ?>
                    <p>✓ User photo is pinned and ready</p>
                <?php endif; ?>

                <?php if (!$pin_jewelry): ?>
                    <label for="jewelry_photo">Upload Jewelry Photo:</label><br>
                    <input type="file" id="jewelry_photo" name="jewelry_photo" accept="image/*" required><br><br>
                <?php else: ?>
                    <p>✓ Jewelry photo is pinned and ready</p>
                <?php endif; ?>

                <button type="submit">
                    <?php if ($pin_user && $pin_jewelry): ?>
                        Process with Pinned Photos
                    <?php elseif ($pin_user): ?>
                        Upload Jewelry & Process
                    <?php elseif ($pin_jewelry): ?>
                        Upload User Photo & Process
                    <?php else: ?>
                        Upload Photos
                    <?php endif; ?>
                </button>
            </form>

            <?php if ($pin_user || $pin_jewelry): ?>
                <div class="buttons-area">
                    <form action="" method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="reset">
                        <input type="hidden" name="pin_user" value="off">
                        <input type="hidden" name="pin_jewelry" value="off">
                        <button type="submit" class="reset-button">Clear All PINs & Start Fresh</button>
                    </form>
                </div>
            <?php endif; ?>
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
