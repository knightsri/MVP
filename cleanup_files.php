<?php
/**
 * Automated File Cleanup Script
 *
 * This script removes old files from the uploads directory that are older
 * than the specified time threshold. It's designed to be run via crontab
 * for automated cleanup.
 *
 * Usage: php cleanup_files.php
 * Cron example: 0 2 * * * cd /path/to/your/app && php cleanup_files.php >> cleanup.log 2>&1
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

// Cleanup configuration - you can modify these values
$cleanup_config = [
    'max_age_hours' => 24,          // Files older than this many hours will be deleted
    'target_directory' => 'uploads/', // Directory to clean up
    'log_file' => 'logs/cleanup.log', // Log file path
    'dry_run' => false,             // Set to true to preview without deleting
    'simulate_now' => false,        // Set to true to force cleanup even for new files (for testing)
    'exclude_patterns' => [         // Files matching these patterns won't be deleted
        '.htaccess',
        'index.php',
        'default.*'
    ]
];

/**
 * Main cleanup function
 */
function cleanup_old_files($config) {
    $target_dir = __DIR__ . '/' . $config['target_directory'];
    $max_age_seconds = $config['max_age_hours'] * 3600;
    $now = time();

    // Log start of cleanup
    cleanup_log("Starting cleanup process in: $target_dir", $config);
    cleanup_log("Max file age: {$config['max_age_hours']} hours ({$max_age_seconds} seconds)", $config);
    cleanup_log("-DRY RUN: " . ($config['dry_run'] ? 'YES' : 'NO'), $config);

    if (!is_dir($target_dir)) {
        cleanup_log("ERROR: Target directory does not exist: $target_dir", $config);
        return false;
    }

    // Get all files in the directory
    $files = scandir($target_dir);
    $deleted_count = 0;
    $total_size_freed = 0;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $file_path = $target_dir . $file;

        // Skip if not a file
        if (!is_file($file_path)) {
            continue;
        }

        // Check if file should be excluded
        if (should_exclude_file($file, $config['exclude_patterns'])) {
            cleanup_log("SKIPPED (excluded): $file", $config);
            continue;
        }

        // Get file modification time
        $file_mtime = filemtime($file_path);
        $file_age_seconds = $now - $file_mtime;

        // Override for simulation mode
        if ($config['simulate_now']) {
            $file_age_seconds = $max_age_seconds + 1; // Force file to be "old"
        }

        if ($file_age_seconds > $max_age_seconds) {
            $file_size = filesize($file_path);
            $file_age_hours = round($file_age_seconds / 3600, 2);

            if ($config['dry_run']) {
                cleanup_log("WOULD DELETE: $file (age: {$file_age_hours}h, size: {$file_size} bytes)", $config);
            } else {
                if (unlink($file_path)) {
                    cleanup_log("DELETED: $file (age: {$file_age_hours}h, size: ".format_bytes($file_size).")", $config);
                    $deleted_count++;
                    $total_size_freed += $file_size;
                } else {
                    cleanup_log("ERROR: Failed to delete $file", $config);
                }
            }
        } else {
            $file_age_hours = round($file_age_seconds / 3600, 2);
            cleanup_log("KEPT: $file (age: {$file_age_hours}h)", $config);
        }
    }

    // Log summary
    cleanup_log("Cleanup completed. Files " . ($config['dry_run'] ? 'would be' : '') . " deleted: $deleted_count", $config);
    if ($total_size_freed > 0) {
        cleanup_log("Total space " . ($config['dry_run'] ? 'would be' : '') . " freed: " . format_bytes($total_size_freed), $config);
    }

    cleanup_log("===========================================", $config);
    return true;
}

/**
 * Check if a file should be excluded from cleanup
 */
function should_exclude_file($filename, $exclude_patterns) {
    foreach ($exclude_patterns as $pattern) {
        if (fnmatch($pattern, $filename)) {
            return true;
        }
    }
    return false;
}

/**
 * Format bytes into human readable format
 */
function format_bytes($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } else {
        $bytes = $bytes . ' bytes';
    }
    return $bytes;
}

/**
 * Logging function
 */
function cleanup_log($message, $config) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";

    echo $log_message; // Output to console for crontab logs

    // Also write to log file if configured
    if (!empty($config['log_file'])) {
        $log_dir = dirname($config['log_file']);
        if (!is_dir($log_dir) && !mkdir($log_dir, 0755, true)) {
            echo "ERROR: Could not create log directory: $log_dir\n";
            return;
        }

        file_put_contents($config['log_file'], $log_message, FILE_APPEND | LOCK_EX);
    }
}

// Determine if script is running via CLI or web request
function is_cli() {
    return php_sapi_name() === 'cli' || defined('STDIN');
}

// Parse command line arguments
$cli_args = is_cli() ? getopt('', ['dry-run', 'simulate-now', 'max-age::', 'log-file::']) : [];

// Override config with command line arguments
if (isset($cli_args['dry-run'])) {
    $cleanup_config['dry_run'] = true;
}

if (isset($cli_args['simulate-now'])) {
    $cleanup_config['simulate_now'] = true;
    $cleanup_config['dry_run'] = true; // Force dry run when simulating
}

if (isset($cli_args['max-age'])) {
    $hours = intval($cli_args['max-age']);
    if ($hours > 0) {
        $cleanup_config['max_age_hours'] = $hours;
    }
}

if (isset($cli_args['log-file'])) {
    $cleanup_config['log_file'] = $cli_args['log-file'];
}

// Display help information
if (isset($cli_args['help']) || isset($cli_args['h'])) {
    echo "\nFile Cleanup Script Help:\n\n";
    echo "Usage: php cleanup_files.php [options]\n\n";
    echo "Options:\n";
    echo "  --dry-run          Show what would be deleted without actually deleting\n";
    echo "  --simulate-now     Force simulation mode (for testing with new files)\n";
    echo "  --max-age=hours    Override default cleanup age in hours\n";
    echo "  --log-file=file    Override default log file path\n";
    echo "  --help, -h         Show this help message\n\n";
    echo "Examples:\n";
    echo "  php cleanup_files.php --dry-run\n";
    echo "  php cleanup_files.php --max-age=48\n";
    echo "  php cleanup_files.php --simulate-now\n\n";
    echo "Cron setup example:\n";
    echo "  0 2 * * * cd /path/to/your/app && php cleanup_files.php --max-age=24 >> cleanup.log 2>&1\n\n";
    exit(0);
}

// Execute cleanup
try {
    cleanup_log("===========================================", $cleanup_config);
    cleanup_log("FILE CLEANUP SCRIPT STARTED", $cleanup_config);

    if ($cleanup_config['simulate_now']) {
        cleanup_log("NOTICE: Running in SIMULATION MODE (--simulate-now flag detected)", $cleanup_config);
    }

    $success = cleanup_old_files($cleanup_config);

    if ($success) {
        cleanup_log("SCRIPT COMPLETED SUCCESSFULLY", $cleanup_config);
        exit(0);
    } else {
        cleanup_log("SCRIPT FAILED", $cleanup_config);
        exit(1);
    }

} catch (Exception $e) {
    cleanup_log("EXCEPTION: " . $e->getMessage(), $cleanup_config);
    exit(1);
}
?>
