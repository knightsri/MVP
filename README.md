# Jewelry Try-On Application

A PHP-based web application for virtual jewelry try-on functionality.

## Features

- Upload user photos and jewelry images
- Process virtual try-on via webhook integration
- Secure file handling and validation
- Automated file cleanup to prevent disk space issues
- Comprehensive logging and error handling

## Project Structure

```
.
├── jewelry_tryon.php      # Main application file
├── config.php            # Configuration settings
├── functions.php         # Common functions and utilities
├── cleanup_files.php     # Automated file cleanup script
├── template.php          # HTML template
├── styles.css           # CSS styling
├── uploads/             # Uploaded files directory (auto-created)
├── logs/               # Log files directory (auto-created)
└── .git/               # Git repository
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

## Installation

1. Clone or extract files to web directory
2. Ensure PHP has write access to `uploads/` and `logs/` directories
3. Configure webhook URL in `config.php`
4. Set up cleanup cron job as described above

## Requirements

- PHP 7.4 or higher
- Fileinfo extension (for MIME type detection)
- cURL extension (for webhook calls)
- Write permissions for uploads/ and logs/ directories

## Security Considerations

- Upload directory is protected by `.htaccess` (deny from all)
- File validation prevents malicious uploads
- Path traversal protection
- Web access blocked for cleanup script
- Logging for security auditing
