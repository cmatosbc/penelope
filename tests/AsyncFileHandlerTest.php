<?php

namespace Penelope\Tests;

use PHPUnit\Framework\TestCase;
use Penelope\AsyncFileHandler;
use RuntimeException;

class AsyncFileHandlerTest extends TestCase
{
    private string $testDir;
    private string $testFile;
    private string $testData;
    
    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/penelope_tests_' . uniqid();
        mkdir($this->testDir);
        $this->testFile = $this->testDir . '/test.txt';
        
        // Create test data (100KB)
        $this->testData = str_repeat('abcdefghij', 10240); // 10 chars * 10240 = 102400 bytes
        file_put_contents($this->testFile, $this->testData);
    }
    
    protected function tearDown(): void
    {
        @unlink($this->testFile);
        @rmdir($this->testDir);
    }

    public function testAsyncRead()
    {
        $handler = new AsyncFileHandler($this->testFile, 'r', 1024); // 1KB chunks
        $fiber = $handler->readAsync();
        
        $receivedData = '';
        $chunks = 0;
        
        // Start the fiber
        $chunk = $fiber->start();
        if ($chunk !== null) {
            $receivedData .= $chunk;
            $chunks++;
        }
        
        // Process chunks until complete
        while ($fiber->isSuspended()) {
            $chunk = $fiber->resume();
            if ($chunk !== null) {
                $this->assertIsString($chunk);
                $this->assertLessThanOrEqual(1024, strlen($chunk));
                $receivedData .= $chunk;
                $chunks++;
            }
        }
        
        // Verify the read operation
        $this->assertGreaterThan(1, $chunks, 'Should have received multiple chunks');
        $this->assertEquals($this->testData, $receivedData, 'Complete data should match');
    }

    public function testAsyncWrite()
    {
        $outputFile = $this->testDir . '/output.txt';
        $handler = new AsyncFileHandler($outputFile, 'w', 1024); // 1KB chunks
        
        $fiber = $handler->writeAsync($this->testData);
        $progressUpdates = 0;
        $lastProgress = 0;
        
        // Start the fiber
        $result = $fiber->start();
        if ($result !== null) {
            $this->assertProgress($result, $lastProgress);
            $lastProgress = $result['progress'];
            $progressUpdates++;
        }
        
        // Process write operations until complete
        while ($fiber->isSuspended()) {
            $result = $fiber->resume();
            if ($result !== null) {
                $this->assertProgress($result, $lastProgress);
                $lastProgress = $result['progress'];
                $progressUpdates++;
            }
        }
        
        // Verify the write operation
        $this->assertGreaterThan(1, $progressUpdates, 'Should have received multiple progress updates');
        $this->assertEquals($this->testData, file_get_contents($outputFile), 'Written data should match');
        
        // Clean up
        @unlink($outputFile);
    }

    private function assertProgress(array $result, float $lastProgress): void
    {
        $this->assertArrayHasKey('bytesWritten', $result);
        $this->assertArrayHasKey('totalWritten', $result);
        $this->assertArrayHasKey('progress', $result);
        $this->assertGreaterThanOrEqual($lastProgress, $result['progress']);
    }

    public function testTransformCallback()
    {
        $handler = new AsyncFileHandler($this->testFile, 'r', 1024);
        $handler->setTransformCallable(function($chunk) {
            return strtoupper($chunk);
        });
        
        $fiber = $handler->readAsync();
        $receivedData = '';
        
        // Start the fiber
        $chunk = $fiber->start();
        if ($chunk !== null) {
            $this->assertEquals(strtoupper($chunk), $chunk);
            $receivedData .= $chunk;
        }
        
        while ($fiber->isSuspended()) {
            $chunk = $fiber->resume();
            if ($chunk !== null) {
                // Each chunk should be uppercase
                $this->assertEquals(strtoupper($chunk), $chunk);
                $receivedData .= $chunk;
            }
        }
        
        // Complete transformed data should match uppercase original
        $this->assertEquals(strtoupper($this->testData), $receivedData);
    }

    public function testSyncOperations()
    {
        $handler = new AsyncFileHandler($this->testFile);
        
        // Test sync read
        $content = $handler->readSync();
        $this->assertEquals($this->testData, $content);
        
        // Test sync write
        $outputFile = $this->testDir . '/sync_output.txt';
        $writeHandler = new AsyncFileHandler($outputFile, 'w');
        $written = $writeHandler->writeSync($this->testData);
        
        $this->assertEquals(strlen($this->testData), $written);
        $this->assertEquals($this->testData, file_get_contents($outputFile));
        
        @unlink($outputFile);
    }
}
