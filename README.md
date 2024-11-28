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

## 📋 Requirements

- PHP 8.1 or higher (Fiber support required)
- Composer for dependency management

## 🛠 Installation

```bash
composer require your-vendor/penelope
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

### Basic File Writing

```php
$handler = new AsyncFileHandler('output.txt', 'w');

// Synchronous write
$written = $handler->writeSync($data);

// Asynchronous write with progress tracking
$fiber = $handler->writeAsync($data);

$progress = $fiber->start();
while ($fiber->isSuspended()) {
    $progress = $fiber->resume();
    if ($progress !== null) {
        echo "Progress: {$progress['progress']}%\n";
    }
}
```

### Data Transformation

```php
$handler = new AsyncFileHandler('data.txt', 'r');

// Set up a transformation (e.g., remove whitespace)
$handler->setTransformCallable(function(string $chunk): string {
    return preg_replace('/\s+/', '', $chunk);
});

// Read with transformation
$fiber = $handler->readAsync();
$content = '';

$chunk = $fiber->start();
while ($chunk !== null || $fiber->isSuspended()) {
    if ($chunk !== null) {
        $content .= $chunk;  // Chunk is already transformed
    }
    $chunk = $fiber->resume();
}
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

### 2. Real-time Data Transformation
Ideal for data sanitization, format conversion, or content filtering:

```php
$handler = new AsyncFileHandler('input.csv', 'r');
$handler->setTransformCallable(function($chunk) {
    return str_replace(',', ';', $chunk);  // Convert CSV to semicolon-separated
});
```

### 3. Progress Monitoring
Perfect for long-running file operations in web applications:

```php
$handler = new AsyncFileHandler('large_file.txt', 'w');
$fiber = $handler->writeAsync($data);

while ($fiber->isSuspended()) {
    $progress = $fiber->resume();
    if ($progress !== null) {
        updateProgressBar($progress['progress']);
    }
}
```

## 🔍 Performance

Based on our benchmarks with a 100MB file:

- **Async Read**: ~3.4x faster than synchronous read
- **Async Write**: Comparable to synchronous write
- **Memory Usage**: Consistent across operations
- **Chunk Size**: Default 8KB (configurable)

## 🧪 Testing

```bash
composer install
./vendor/bin/phpunit
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
