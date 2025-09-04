# Jewelry Try-On Application

A PHP-based web application for virtual jewelry try-on functionality.

## Features

- Upload user photos and jewelry images
- Automatic image optimization for **better performance**
- Process virtual try-on via webhook integration
- Secure file handling and validation
- Automated file cleanup to prevent disk space issues
- Comprehensive logging and error handling

## Project Structure

```
.
├── jewelry_tryon.php          # Main application file
├── config.php                # Configuration settings
├── functions.php             # Common functions and utilities
├── cleanup_files.php         # Automated file cleanup script
├── test_image_optimization.php # Image optimization testing script
├── template.php              # HTML template
├── styles.css               # CSS styling
├── uploads/                 # Uploaded files directory (auto-created)
├── logs/                   # Log files directory (auto-created)
└── .git/                   # Git repository
```

## Automated File Cleanup

### Overview

The application includes an automated cleanup system to prevent uploaded files from accumulating indefinitely. The `cleanup_files.php` script removes files older than a configurable threshold.

### Configuration

Edit the cleanup configuration in `cleanup_files.php`:

```php
$cleanup_config = [
    'max_age_hours' => 24,          // Files older than 24 hours will be deleted
    'target_directory' => 'uploads/', // Directory to clean up
    'log_file' => 'logs/cleanup.log', // Cleanup log file
    'dry_run' => false,            // Preview mode (set to true for testing)
    'exclude_patterns' => [        // Files to keep
        '.htaccess',
        'index.php',
        'default.*'
    ]
];
```

### Usage

#### Manual Testing

1. **Dry Run** (preview what will be deleted):
   ```bash
   php cleanup_files.php --dry-run
   ```

2. **Force Simulation** (for testing with new files):
   ```bash
   php cleanup_files.php --simulate-now
   ```

3. **Custom Age Limit**:
   ```bash
   php cleanup_files.php --max-age=48
   ```

4. **Custom Log File**:
   ```bash
   php cleanup_files.php --log-file=custom.log
   ```

TO VIEW HELP:
   ```bash
   php cleanup_files.php --help
   ```

#### Automated Cron Job Setup

1. **Daily at 2 AM** (recommended for production):
   ```bash
   # Edit crontab
   crontab -e

   # Add this line (replace /path/to/your/app with actual path)
   0 2 * * * cd /path/to/your/app && php cleanup_files.php >> cleanup.log 2>&1
   ```

2. **Every 6 hours**:
   ```bash
   0 */6 * * * cd /path/to/your/app && php cleanup_files.php --max-age=6 >> cleanup.log 2>&1
   ```

3. **Weekly on Sunday** (longer retention):
   ```bash
   0 3 * * 0 cd /path/to/your/app && php cleanup_files.php --max-age=168 >> cleanup.log 2>&1
   ```

#### Command Line Options

- `--dry-run`: Preview deletions without actually deleting files
- `--simulate-now`: Force simulation mode for testing
- `--max-age=hours`: Override default retention period
- `--log-file=path`: Use custom log file path
- `--help, -h`: Display help information

### Security Features

- Web access prevention (script only runs via CLI)
- Path traversal protection
- File exclusion patterns to protect system files
- Comprehensive logging for audit trails

### Log Files

Cleanup activities are logged to:
- `logs/cleanup.log`: Detailed cleanup operations
- Console output: For crontab email notifications

Example log output:
```
[2025-01-04 14:30:15] FILE CLEANUP SCRIPT STARTED
[2025-01-04 14:30:15] Starting cleanup process in: /app/uploads/
[2025-01-04 14:30:15] Max file age: 24 hours (86400 seconds)
[2025-01-04 14:30:15] -DRY RUN: NO
[2025-01-04 14:30:15] DELETED: img_20250103_123456_abc123.jpg (age: 25.5h, size: 2.5 MB)
[2025-01-04 14:30:15] KEPT: img_20250104_143000_def456.jpg (age: 2.3h)
[2025-01-04 14:30:15] Cleanup completed. Files deleted: 5
[2025-01-04 14:30:15] Total space freed: 12.8 MB
[2025-01-04 14:30:15] SCRIPT COMPLETED SUCCESSFULLY
```

### Monitoring

1. **Check log file**:
   ```bash
   tail -f logs/cleanup.log
   ```

2. **Monitor disk space**:
   ```bash
   du -sh uploads/
   ```

3. **Count files by age**:
   ```bash
   find uploads/ -type f -mtime +1 | wc -l  # Files older than 24 hours
   ```

## Automatic Image Optimization

### Overview

The application automatically optimizes images during upload to improve performance and reduce bandwidth usage. Images are resized and compressed while maintaining quality, reducing file sizes by up to 70-80% for large photos.

### Performance Benefits

- **Faster page loading**: Smaller images load quicker
- **Reduced bandwidth**: Less data transfer for users
- **Better user experience**: Faster upload processing
- **Lower storage costs**: Optimized images take less space
- **Improved accessibility**: Faster loading on mobile devices

### Configuration

Optimize image settings in `config.php`:

```php
// Image optimization constants
define('IMAGE_MAX_WIDTH', 1200);        // Maximum width in pixels
define('IMAGE_MAX_HEIGHT', 1200);       // Maximum height in pixels
define('IMAGE_JPEG_QUALITY', 85);       // JPEG compression (0-100, higher = better quality)
define('IMAGE_PNG_COMPRESSION', 6);     // PNG compression (0-9, higher = smaller file)
define('IMAGE_WEBP_QUALITY', 80);       // WebP quality (if supported)
define('IMAGE_BACKUP_ORIGINAL', true);  // Keep original files as backup
```

### How It Works

1. **Automatic Detection**: Checks if uploaded images exceed size limits
2. **Smart Resizing**: Maintains aspect ratio while fitting within max dimensions
3. **Format-Specific Optimization**:
   - **JPEG/JPG**: Quality-based compression with EXIF orientation correction
   - **PNG**: Lossless compression with alpha channel preservation
   - **GIF**: Frame optimization with transparency support
4. **Backup Preservation**: Optional backup of original files (*.original extension)
5. **Detailed Logging**: All optimization activities logged for monitoring

### Supported Formats

- ✅ JPEG/JPG (with EXIF orientation handling)
- ✅ PNG (with alpha channel support)
- ✅ GIF (with transparency support)

### Testing & Verification

Use the included test script to verify optimization:

```bash
# Run comprehensive optimization tests
php test_image_optimization.php

# Test specific scenarios
php test_image_optimization.php --test=jpeg
php test_image_optimization.php --test=png
```

**Example Test Output:**
```
=== IMAGE OPTIMIZATION CONFIGURATION ===
Max width: 1200px
Max height: 1200px
JPEG quality: 85/100
PNG compression: 6/9
Backup originals: Yes
Supported formats: JPEG, PNG, GIF

=== RUNNING IMAGE OPTIMIZATION TESTS ===

Test 1/5: PASSED - 400x300 jpeg (size: 15.2 KB no change)
Test 2/5: PASSED - 2000x1500 jpeg (size: 487.3 KB → 89.5 KB savings: 81.6%) [backup created]
Test 3/5: PASSED - 1600x1200 png (size: 2.1 MB → 487.2 KB savings: 77.1%)
Test 4/5: PASSED - 1400x1000 gif (size: 856.4 KB → 234.1 KB savings: 72.7%)
Test 5/5: PASSED - 800x2000 jpeg (size: 321.8 KB → 85.3 KB savings: 73.5%)

=== TEST RESULTS ===
Total tests: 5
Successful tests: 5
Success rate: 100.0%
✅ All optimization tests passed!
```

### Monitoring Optimization

1. **Check optimization logs**:
   ```bash
   tail -f logs/errors.log | grep "IMAGE_OPTIMIZATION"
   ```

2. **Monitor image sizes**:
   ```bash
   find uploads/ -name "*.jpg" -exec ls -lh {} \; | head -10
   ```

3. **Analyze disk savings**:
   ```bash
   # Show original vs optimized sizes (if backups exist)
   find uploads/ -name "*.original" -exec bash -c 'echo -n "$1 -> "; ls -lh ${1%.original}' _ {} \;
   ```

### Log Analysis

Optimization activities are logged with detailed information:

```
[2025-01-04 14:25:15] [INFO] [IMAGE_OPTIMIZATION] Image optimization: Starting - img_20250104_142515_xyz123.jpg (2048x1536, 2457600 bytes)
[2025-01-04 14:25:15] [INFO] [IMAGE_OPTIMIZATION] Image optimization: Completed - img_20250104_142515_xyz123.jpg (saved 1830400 bytes, 74.5%)
[2025-01-04 14:25:16] [INFO] [UPLOAD] Optimized images - User: 1200x900 (425.8 KB), Jewelry: 1200x800 (312.4 KB)
```

## Installation

1. Clone or extract files to web directory
2. Ensure PHP has write access to `uploads/` and `logs/` directories
3. Configure webhook URL in `config.php`
4. Set up cleanup cron job as described above

## Requirements

- PHP 7.4 or higher with GD extension (for image optimization)
- Fileinfo extension (for MIME type detection)
- cURL extension (for webhook calls)
- Write permissions for uploads/ and logs/ directories

## Security Considerations

- Upload directory is protected by `.htaccess` (deny from all)
- File validation prevents malicious uploads
- Path traversal protection
- Web access blocked for cleanup script
- Logging for security auditing
