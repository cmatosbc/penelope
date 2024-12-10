<?php

namespace Penelope\Tests\Compression;

use PHPUnit\Framework\TestCase;
use Penelope\Compression\CompressionHandler;
use RuntimeException;

class CompressionHandlerTest extends TestCase
{
    private string $testData;

    protected function setUp(): void
    {
        $this->testData = str_repeat('Hello, World! ', 1000);
    }

    public function testGzipCompression()
    {
        $handler = new CompressionHandler('gzip');
        
        $compressed = $handler->compress($this->testData);
        $this->assertNotEquals($this->testData, $compressed);
        $this->assertLessThan(strlen($this->testData), strlen($compressed));
        
        $decompressed = $handler->decompress($compressed);
        $this->assertEquals($this->testData, $decompressed);
    }

    public function testBzip2Compression()
    {
        $handler = new CompressionHandler('bzip2');
        
        $compressed = $handler->compress($this->testData);
        $this->assertNotEquals($this->testData, $compressed);
        $this->assertLessThan(strlen($this->testData), strlen($compressed));
        
        $decompressed = $handler->decompress($compressed);
        $this->assertEquals($this->testData, $decompressed);
    }

    public function testDeflateCompression()
    {
        $handler = new CompressionHandler('deflate');
        
        $compressed = $handler->compress($this->testData);
        $this->assertNotEquals($this->testData, $compressed);
        $this->assertLessThan(strlen($this->testData), strlen($compressed));
        
        $decompressed = $handler->decompress($compressed);
        $this->assertEquals($this->testData, $decompressed);
    }

    public function testInvalidAlgorithm()
    {
        $this->expectException(RuntimeException::class);
        new CompressionHandler('invalid');
    }

    public function testInvalidCompressionLevel()
    {
        $this->expectException(RuntimeException::class);
        new CompressionHandler('gzip', 10);
    }

    public function testFileExtensions()
    {
        $gzip = new CompressionHandler('gzip');
        $this->assertEquals('.gz', $gzip->getFileExtension());

        $bzip2 = new CompressionHandler('bzip2');
        $this->assertEquals('.bz2', $bzip2->getFileExtension());

        $deflate = new CompressionHandler('deflate');
        $this->assertEquals('.zz', $deflate->getFileExtension());
    }
}
