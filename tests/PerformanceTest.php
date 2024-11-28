<?php

namespace Penelope\Tests;

use PHPUnit\Framework\TestCase;
use Penelope\AsyncFileHandler;

class PerformanceTest extends TestCase
{
    private string $testDir;
    private string $largeFile;
    private string $transformFile;
    private string $outputFile;
    private int $fileSize = 100 * 1024 * 1024; // 100MB for regular tests
    private int $transformFileSize = 10 * 1024 * 1024; // 10MB for transform tests
    private int $chunkSize = 8192; // 8KB chunks
    
    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/penelope_perf_' . uniqid();
        mkdir($this->testDir);
        $this->largeFile = $this->testDir . '/large.dat';
        $this->transformFile = $this->testDir . '/transform.dat';
        $this->outputFile = $this->testDir . '/output.dat';
        
        // Create test files
        $this->createRandomFile($this->largeFile, $this->fileSize);
        $this->createTestFileWithWhitespace($this->transformFile, $this->transformFileSize);
    }
    
    protected function tearDown(): void
    {
        @unlink($this->largeFile);
        @unlink($this->transformFile);
        @unlink($this->outputFile);
        @rmdir($this->testDir);
    }
    
    private function createRandomFile(string $path, int $size): void
    {
        $file = fopen($path, 'wb');
        $remaining = $size;
        $bufferSize = min(1024 * 1024, $size); // 1MB buffer or file size if smaller
        
        while ($remaining > 0) {
            $writeSize = min($bufferSize, $remaining);
            $data = random_bytes($writeSize);
            fwrite($file, $data);
            $remaining -= $writeSize;
        }
        
        fclose($file);
    }
    
    private function createTestFileWithWhitespace(string $path, int $size): void
    {
        $file = fopen($path, 'wb');
        $remaining = $size;
        
        // Create a pattern with whitespace
        $pattern = "Hello   World!\n   This   is   a   test   file   with   extra   spaces.\n";
        $patternLength = strlen($pattern);
        
        while ($remaining > 0) {
            $writeSize = min($patternLength, $remaining);
            fwrite($file, substr($pattern, 0, $writeSize));
            $remaining -= $writeSize;
        }
        
        fclose($file);
    }
    
    private function measureTime(callable $operation): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $chunkCount = $operation();
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        return [
            'time' => ($endTime - $startTime),
            'memory' => ($endMemory - $startMemory),
            'chunks' => $chunkCount ?? 0
        ];
    }
    
    public function testTransformReadPerformance()
    {
        $handler = new AsyncFileHandler($this->transformFile, 'r', $this->chunkSize);
        
        // Set up handler with a more complex transformation
        $handler->setTransformCallable(function(string $chunk): string {
            // More complex transformation that simulates real-world processing
            $chunk = preg_replace('/\s+/', '', $chunk); // Remove whitespace
            $chunk = str_rot13($chunk); // Apply ROT13 encoding
            $chunk = base64_encode($chunk); // Base64 encode
            $chunk = strtr($chunk, '+/', '-_'); // URL-safe base64
            return $chunk;
        });
        
        // Measure sync read with transform
        $syncMetrics = $this->measureTime(function() use ($handler) {
            $content = $handler->readSync();
            $this->assertStringNotContainsString(' ', $content, 'Sync read should have removed all whitespace');
            $this->assertStringNotContainsString("\n", $content, 'Sync read should have removed all newlines');
            return 1; // Sync operation processes file as one chunk
        });
        
        // Measure async read with transform
        $asyncMetrics = $this->measureTime(function() use ($handler) {
            $fiber = $handler->readAsync();
            $totalRead = '';
            $chunks = 0;
            
            $chunk = $fiber->start();
            if ($chunk !== null) {
                $chunks++;
                $this->assertStringNotContainsString(' ', $chunk, 'Chunk should have no whitespace');
                $this->assertStringNotContainsString("\n", $chunk, 'Chunk should have no newlines');
                $totalRead .= $chunk;
            }
            
            while ($fiber->isSuspended()) {
                $chunk = $fiber->resume();
                if ($chunk !== null) {
                    $chunks++;
                    $this->assertStringNotContainsString(' ', $chunk, 'Chunk should have no whitespace');
                    $this->assertStringNotContainsString("\n", $chunk, 'Chunk should have no newlines');
                    $totalRead .= $chunk;
                }
            }
            
            return $chunks;
        });
        
        // Output performance metrics
        $this->outputMetrics('Transform Read', $syncMetrics, $asyncMetrics);
    }
    
    public function testTransformWritePerformance()
    {
        // Prepare test data
        $data = file_get_contents($this->transformFile);
        
        // Set up handlers with whitespace removal transformation
        $handler = new AsyncFileHandler($this->outputFile, 'w', $this->chunkSize);
        $handler->setTransformCallable(function(string $chunk): string {
            return preg_replace('/\s+/', '', $chunk);
        });
        
        // Measure sync write with transform
        $syncMetrics = $this->measureTime(function() use ($handler, $data) {
            $written = $handler->writeSync($data);
            $outputContent = file_get_contents($this->outputFile);
            $this->assertStringNotContainsString(' ', $outputContent, 'Output should have no whitespace');
            $this->assertStringNotContainsString("\n", $outputContent, 'Output should have no newlines');
            return 1; // Sync operation processes file as one chunk
        });
        
        // Clean up and prepare for async test
        unlink($this->outputFile);
        
        // Measure async write with transform
        $handler = new AsyncFileHandler($this->outputFile, 'w', $this->chunkSize);
        $handler->setTransformCallable(function(string $chunk): string {
            return preg_replace('/\s+/', '', $chunk);
        });
        
        $asyncMetrics = $this->measureTime(function() use ($handler, $data) {
            $fiber = $handler->writeAsync($data);
            $chunks = 0;
            
            $progress = $fiber->start();
            if ($progress !== null) {
                $chunks++;
            }
            
            while ($fiber->isSuspended()) {
                $progress = $fiber->resume();
                if ($progress !== null) {
                    $chunks++;
                }
            }
            
            $outputContent = file_get_contents($this->outputFile);
            $this->assertStringNotContainsString(' ', $outputContent, 'Output should have no whitespace');
            $this->assertStringNotContainsString("\n", $outputContent, 'Output should have no newlines');
            
            return $chunks;
        });
        
        // Output performance metrics
        $this->outputMetrics('Transform Write', $syncMetrics, $asyncMetrics);
    }
    
    public function testReadPerformance()
    {
        $handler = new AsyncFileHandler($this->largeFile, 'r', $this->chunkSize);
        
        // Measure sync read
        $syncMetrics = $this->measureTime(function() use ($handler) {
            $content = $handler->readSync();
            $this->assertEquals($this->fileSize, strlen($content));
            return 1; // Sync operation processes file as one chunk
        });
        
        // Measure async read
        $asyncMetrics = $this->measureTime(function() use ($handler) {
            $fiber = $handler->readAsync();
            $totalRead = 0;
            $chunks = 0;
            
            $chunk = $fiber->start();
            if ($chunk !== null) {
                $chunks++;
                $totalRead += strlen($chunk);
            }
            
            while ($fiber->isSuspended()) {
                $chunk = $fiber->resume();
                if ($chunk !== null) {
                    $chunks++;
                    $totalRead += strlen($chunk);
                }
            }
            
            $this->assertEquals($this->fileSize, $totalRead);
            return $chunks;
        });
        
        // Output performance metrics
        $this->outputMetrics('Read', $syncMetrics, $asyncMetrics);
    }
    
    public function testWritePerformance()
    {
        // Prepare test data
        $data = file_get_contents($this->largeFile);
        $this->assertEquals($this->fileSize, strlen($data));
        
        // Measure sync write
        $handler = new AsyncFileHandler($this->outputFile, 'w', $this->chunkSize);
        $syncMetrics = $this->measureTime(function() use ($handler, $data) {
            $written = $handler->writeSync($data);
            $this->assertEquals($this->fileSize, $written);
            $this->assertEquals($this->fileSize, filesize($this->outputFile));
            return 1; // Sync operation processes file as one chunk
        });
        
        // Clean up and prepare for async test
        unlink($this->outputFile);
        
        // Measure async write
        $handler = new AsyncFileHandler($this->outputFile, 'w', $this->chunkSize);
        $asyncMetrics = $this->measureTime(function() use ($handler, $data) {
            $fiber = $handler->writeAsync($data);
            $totalWritten = 0;
            $chunks = 0;
            
            $progress = $fiber->start();
            if ($progress !== null) {
                $chunks++;
                $totalWritten = $progress['totalWritten'];
            }
            
            while ($fiber->isSuspended()) {
                $progress = $fiber->resume();
                if ($progress !== null) {
                    $chunks++;
                    $totalWritten = $progress['totalWritten'];
                }
            }
            
            $this->assertEquals($this->fileSize, $totalWritten);
            $this->assertEquals($this->fileSize, filesize($this->outputFile));
            return $chunks;
        });
        
        // Output performance metrics
        $this->outputMetrics('Write', $syncMetrics, $asyncMetrics);
    }
    
    public function testTransformationDemo()
    {
        // Create a small test file with specific content
        $testContent = "  Hello  World!  \n  This  has  extra  spaces  \n";
        file_put_contents($this->transformFile, $testContent);
        
        echo "\nTransformation Demo:\n";
        echo "Original content:\n";
        echo "'" . $testContent . "'";
        
        $handler = new AsyncFileHandler($this->transformFile, 'r', $this->chunkSize);
        $handler->setTransformCallable(function(string $chunk): string {
            return preg_replace('/\s+/', '', $chunk);
        });
        
        // Test sync read
        $syncResult = $handler->readSync();
        echo "\n\nAfter sync read + transform:\n";
        echo "'" . $syncResult . "'";
        
        // Test async read
        $fiber = $handler->readAsync();
        $asyncResult = '';
        
        $chunk = $fiber->start();
        if ($chunk !== null) {
            $asyncResult .= $chunk;
        }
        
        while ($fiber->isSuspended()) {
            $chunk = $fiber->resume();
            if ($chunk !== null) {
                $asyncResult .= $chunk;
            }
        }
        
        echo "\n\nAfter async read + transform:\n";
        echo "'" . $asyncResult . "'";
        echo "\n";
        
        // Verify both methods produce the same result
        $this->assertEquals($syncResult, $asyncResult, 'Sync and async transforms should match');
        $this->assertStringNotContainsString(' ', $syncResult, 'Should have no spaces');
        $this->assertStringNotContainsString("\n", $syncResult, 'Should have no newlines');
    }
    
    private function outputMetrics(string $operation, array $syncMetrics, array $asyncMetrics): void
    {
        // Calculate ratios, handling zero values
        $timeRatio = $syncMetrics['time'] > 0 ? $asyncMetrics['time'] / $syncMetrics['time'] : 0;
        
        // For memory, if both are 0, ratio is 1, if only sync is 0, ratio is INF
        $memoryRatio = $syncMetrics['memory'] > 0 
            ? $asyncMetrics['memory'] / $syncMetrics['memory']
            : ($asyncMetrics['memory'] > 0 ? INF : 1);
        
        echo "\n{$operation} Performance Metrics:\n";
        echo sprintf("Sync  Time: %.3f seconds, Memory: %.2f MB (1 chunk)\n",
            $syncMetrics['time'],
            $syncMetrics['memory'] / 1024 / 1024
        );
        echo sprintf("Async Time: %.3f seconds, Memory: %.2f MB (%d chunks)\n",
            $asyncMetrics['time'],
            $asyncMetrics['memory'] / 1024 / 1024,
            $asyncMetrics['chunks']
        );
        echo sprintf("Ratios - Time: %.2fx, Memory: %.2fx\n",
            $timeRatio,
            $memoryRatio
        );
        
        // Add assertions to ensure performance meets expectations
        $this->assertLessThan(
            2.0,
            $timeRatio,
            "Async operation should not be more than 2x slower than sync"
        );
        
        // Only assert memory ratio if we have meaningful measurements
        if ($syncMetrics['memory'] > 0 || $asyncMetrics['memory'] > 0) {
            $this->assertLessThan(
                2.0,
                $memoryRatio,
                "Async operation should not use more than 2x memory than sync"
            );
        }
    }
}
