<?php
/**
 * Image Optimization Test Script
 *
 * Tests the image optimization functionality with various scenarios.
 * This script creates test images and verifies optimization works correctly.
 */

// Prevent direct access from web
if (!defined('PHPUNIT_COMPOSER_INSTALL') &&
    !defined('__PHPUNIT_PHAR__') &&
    !defined('DUMP_VIA_CLI')) {
    if (isset($_SERVER['HTTP_HOST'])) {
        die('Direct access not allowed');
    }
}

// Load configuration
require_once 'config.php';

// Test configuration
$test_config = [
    'test_directory' => 'uploads/test/',
    'create_test_images' => true,
    'cleanup_after_test' => true
];

// Ensure test directory exists
if (!is_dir($test_config['test_directory'])) {
    mkdir($test_config['test_directory'], 0755, true);
    echo "Created test directory: {$test_config['test_directory']}\n";
}

/**
 * Create a test image of given dimensions and type
 */
function create_test_image($width, $height, $type = 'jpeg', $filename = null) {
    global $test_config;

    if (!$filename) {
        $filename = "test_{$width}x{$height}." . ($type === 'jpeg' ? 'jpg' : $type);
    }

    $filepath = $test_config['test_directory'] . $filename;

    // Create image resource
    $image = imagecreatetruecolor($width, $height);

    // Add some colors and shapes for testing
    $colors = [
        imagecolorallocate($image, 255, 0, 0),   // Red
        imagecolorallocate($image, 0, 255, 0),   // Green
        imagecolorallocate($image, 0, 0, 255),   // Blue
        imagecolorallocate($image, 255, 255, 0), // Yellow
        imagecolorallocate($image, 255, 0, 255), // Magenta
    ];

    // Fill with gradient or pattern for testing
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $color_index = ($x + $y) % count($colors);
            imagesetpixel($image, $x, $y, $colors[$color_index]);
        }
    }

    // Add some text for identification
    $text_color = imagecolorallocate($image, 255, 255, 255);
    $font_size = min(20, max(8, $width / 50));
    imagestring($image, $font_size, 10, 10, "{$width}x{$height}", $text_color);

    $success = false;

    // Save based on type
    switch ($type) {
        case 'jpeg':
        case 'jpg':
            $success = imagejpeg($image, $filepath, 95); // High quality for test
            break;
        case 'png':
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $success = imagepng($image, $filepath, 0); // No compression for test
            break;
        case 'gif':
            $success = imagegif($image, $filepath);
            break;
    }

    imagedestroy($image);

    if ($success) {
        $size = filesize($filepath);
        echo "Created test image: $filename ({$width}x{$height}, " . format_bytes($size) . ")\n";
        return $filepath;
    } else {
        echo "Failed to create test image: $filename\n";
        return false;
    }
}

/**
 * Run optimization tests
 */
function run_optimization_tests() {
    global $test_config;

    $test_cases = [
        // Small image (should skip optimization)
        ['width' => 400, 'height' => 300, 'type' => 'jpeg', 'should_optimize' => false],
        // Large JPEG (should be optimized)
        ['width' => 2000, 'height' => 1500, 'type' => 'jpeg', 'should_optimize' => true],
        // Large PNG (should be optimized)
        ['width' => 1600, 'height' => 1200, 'type' => 'png', 'should_optimize' => true],
        // Large GIF (should be optimized)
        ['width' => 1400, 'height' => 1000, 'type' => 'gif', 'should_optimize' => true],
        // Portrait image (should be optimized)
        ['width' => 800, 'height' => 2000, 'type' => 'jpeg', 'should_optimize' => true],
    ];

    echo "\n=== RUNNING IMAGE OPTIMIZATION TESTS ===\n\n";

    $total_tests = count($test_cases);
    $successful_tests = 0;

    foreach ($test_cases as $index => $test_case) {
        echo "Test " . ($index + 1) . "/{$total_tests}: ";

        // Create test image
        $test_image_path = create_test_image(
            $test_case['width'],
            $test_case['height'],
            $test_case['type']
        );

        if (!$test_image_path) {
            echo "FAILED - Could not create test image\n";
            continue;
        }

        // Get original stats
        $original_stats = get_image_stats($test_image_path);
        $original_size = $original_stats['size_bytes'];

        // Test optimization
        $optimization_result = optimize_image($test_image_path, basename($test_image_path));

        echo ($optimization_result ? "PASSED" : "FAILED");
        echo " - {$test_case['width']}x{$test_case['height']} {$test_case['type']}";

        if ($optimization_result) {
            $successful_tests++;

            // Check final stats
            $optimized_stats = get_image_stats($test_image_path);
            $optimized_size = $optimized_stats['size_bytes'];
            $size_difference = $original_size - $optimized_size;
            $size_percentage = round(($size_difference / $original_size) * 100, 1);

            echo " (size: " . format_bytes($original_size) . " → " . format_bytes($optimized_size);
            if ($size_difference > 0) {
                echo " savings: {$size_percentage}%)";
            } else {
                echo " no change)";
            }

            // Check if backup was created
            $backup_path = $test_image_path . '.original';
            if (file_exists($backup_path) && IMAGE_BACKUP_ORIGINAL) {
                echo " [backup created]";
            }
        }

        echo "\n";

        // Clean up test files if configured
        if ($test_config['cleanup_after_test']) {
            @unlink($test_image_path);
            $backup_path = $test_image_path . '.original';
            if (file_exists($backup_path)) {
                @unlink($backup_path);
            }
        }
    }

    echo "\n=== TEST RESULTS ===\n";
    echo "Total tests: {$total_tests}\n";
    echo "Successful tests: {$successful_tests}\n";
    echo "Success rate: " . round(($successful_tests / $total_tests) * 100, 1) . "%\n";

    if ($successful_tests === $total_tests) {
        echo "✅ All optimization tests passed!\n";
    } else {
        echo "❌ Some tests failed. Check the logs for details.\n";
    }

    return $successful_tests === $total_tests;
}

/**
 * Display configuration information
 */
function display_configuration() {
    echo "\n=== IMAGE OPTIMIZATION CONFIGURATION ===\n";
    echo "Max width: " . IMAGE_MAX_WIDTH . "px\n";
    echo "Max height: " . IMAGE_MAX_HEIGHT . "px\n";
    echo "JPEG quality: " . IMAGE_JPEG_QUALITY . "/100\n";
    echo "PNG compression: " . IMAGE_PNG_COMPRESSION . "/9\n";
    echo "Backup originals: " . (IMAGE_BACKUP_ORIGINAL ? 'Yes' : 'No') . "\n";
    echo "Supported formats: JPEG, PNG, GIF\n";
}

// Main execution
try {
    display_configuration();

    if (!$test_config['create_test_images']) {
        echo "\nTest image creation is disabled. Enable 'create_test_images' in test_config to run tests.\n";
        exit(0);
    }

    echo "\nStarting optimization tests...\n";

    $test_result = run_optimization_tests();

    if ($test_result) {
        echo "\n🎉 All tests completed successfully!\n";
        exit(0);
    } else {
        echo "\n❌ Some tests failed.\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "\n❌ Test execution failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
