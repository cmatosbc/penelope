# Penelope 🧵

[![PHP Composer](https://github.com/cmatosbc/penelope/actions/workflows/composer.yml/badge.svg)](https://github.com/cmatosbc/penelope/actions/workflows/composer.yml) [![PHP 8.2 PHPUnit Tests](https://github.com/cmatosbc/penelope/actions/workflows/phpunit.yml/badge.svg)](https://github.com/cmatosbc/penelope/actions/workflows/phpunit.yml)

A high-performance asynchronous file handling library for PHP, leveraging Fibers for non-blocking I/O operations.

## 🚀 Overview

Penelope is designed to handle large file operations efficiently by utilizing PHP's Fiber feature for asynchronous processing. It breaks down file operations into manageable chunks, allowing for better memory management and improved performance, especially for large files.

### Why Penelope?

- **Memory Efficient**: Process large files without loading them entirely into memory
- **Non-Blocking**: Leverage PHP Fibers for asynchronous operations
- **Flexible**: Support for both synchronous and asynchronous operations
- **Transformable**: Apply custom transformations during read/write operations
- **Progress Tracking**: Monitor write progress in real-time
- **Compression Support**: Built-in support for gzip, bzip2, and deflate compression
- **Error Resilience**: Robust error handling with retry mechanisms and logging

## 📋 Requirements

- PHP 8.1 or higher (Fiber support required)
- Composer for dependency management
- PHP Extensions:
  - `zlib` for gzip/deflate compression
  - `bz2` for bzip2 compression (optional)

## 🛠 Installation

```bash
composer require cmatosbc/penelope

# For bzip2 support (Ubuntu/Debian)
sudo apt-get install php-bz2
```

## 📖 Usage

### Basic File Reading

```php
use Penelope\AsyncFileHandler;

// Create a handler instance
$handler = new AsyncFileHandler('large_file.txt', 'r');

// Synchronous read
$content = $handler->readSync();

// Asynchronous read
$fiber = $handler->readAsync();
$content = '';

$chunk = $fiber->start();
if ($chunk !== null) {
    $content .= $chunk;
}

while ($fiber->isSuspended()) {
    $chunk = $fiber->resume();
    if ($chunk !== null) {
        $content .= $chunk;
    }
}
```

### Compression Support

```php
use Penelope\Compression\CompressionHandler;

// Create a compression handler (gzip, bzip2, or deflate)
$compression = new CompressionHandler('gzip', 6); // level 6 compression

// Compress data
$compressed = $compression->compress($data);

// Decompress data
$decompressed = $compression->decompress($compressed);

// Get file extension for compressed files
$extension = $compression->getFileExtension(); // Returns .gz for gzip
```

### Error Handling with Retries

```php
use Penelope\Error\ErrorHandler;
use Penelope\Error\RetryPolicy;
use Psr\Log\LoggerInterface;

// Create a retry policy with custom settings
$retryPolicy = new RetryPolicy(
    maxAttempts: 3,        // Maximum number of retry attempts
    delayMs: 100,          // Initial delay between retries in milliseconds
    backoffMultiplier: 2.0, // Multiplier for exponential backoff
    maxDelayMs: 5000       // Maximum delay between retries
);

// Create an error handler with custom logger (optional)
$errorHandler = new ErrorHandler($logger, $retryPolicy);

// Execute an operation with retry logic
try {
    $result = $errorHandler->executeWithRetry(
        function() {
            // Your operation here
            return $someResult;
        },
        'Reading file chunk'
    );
} catch (\RuntimeException $e) {
    // Handle final failure after all retries
}
```

### Combining Features

```php
use Penelope\AsyncFileHandler;
use Penelope\Compression\CompressionHandler;
use Penelope\Error\ErrorHandler;
use Penelope\Error\RetryPolicy;

// Set up handlers
$compression = new CompressionHandler('gzip');
$retryPolicy = new RetryPolicy(maxAttempts: 3);
$errorHandler = new ErrorHandler(null, $retryPolicy);
$fileHandler = new AsyncFileHandler('large_file.txt', 'r');

// Read and compress file with retry logic
$errorHandler->executeWithRetry(
    function() use ($fileHandler, $compression) {
        $fiber = $fileHandler->readAsync();
        $compressedContent = '';
        
        // Start reading
        $chunk = $fiber->start();
        if ($chunk !== null) {
            $compressedContent .= $compression->compress($chunk);
        }
        
        // Continue reading
        while ($fiber->isSuspended()) {
            $chunk = $fiber->resume();
            if ($chunk !== null) {
                $compressedContent .= $compression->compress($chunk);
            }
        }
        
        // Write compressed content
        file_put_contents('output.gz', $compressedContent);
    },
    'Compressing file'
);
```

## 🎯 Use Cases

### 1. Large File Processing
Perfect for processing large log files, data exports, or any situation where memory efficiency is crucial:

```php
$handler = new AsyncFileHandler('large_log.txt', 'r');
$fiber = $handler->readAsync();

// Process line by line without loading entire file
while ($chunk = $fiber->resume()) {
    // Process chunk
    analyzeLogData($chunk);
}
```

### 2. File Compression and Archiving

- Compress large log files for archival
- Create compressed backups of data files
- Stream compressed data to remote storage
- Process and compress multiple files in parallel

### 3. Error-Resilient Operations

- Retry failed network file transfers
- Handle intermittent I/O errors gracefully
- Log detailed error information for debugging
- Implement progressive backoff for rate-limited operations

## 🔍 Performance

Based on our benchmarks with a 100MB file:

- **Async Read**: ~3.4x faster than synchronous read
- **Async Write**: Comparable to synchronous write
- **Memory Usage**: Consistent across operations
- **Chunk Size**: Default 8KB (configurable)

## 🧪 Testing

```bash
composer install
./vendor/bin/phpunit --testdox
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📝 License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details. This means:

- You can freely use, modify, and distribute this software
- If you modify and distribute this software, you must:
  * Make your modifications available under GPL-3.0
  * Include the original copyright notice
  * Include the full text of the GPL-3.0 license
  * Make your source code available

## ⚠️ Important Notes

- Requires PHP 8.1+ for Fiber support
- Performance may vary based on file size and system configuration
- For optimal performance, adjust chunk size based on your use case

## 🔗 Links

- [PHP Fibers Documentation](https://www.php.net/manual/en/language.fibers.php)
- [Issue Tracker](https://github.com/your-username/penelope/issues)
- [Contributing Guidelines](CONTRIBUTING.md)
