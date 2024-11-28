<?php

namespace Penelope;

use SplFileInfo;
use SplFileObject;
use Fiber;
use RuntimeException;

class AsyncFileHandler
{
    private string $filePath;
    private SplFileObject $fileObject;
    private int $chunkSize;
    /** @var callable|null */
    private $transformCallable;

    /**
     * @param string $filePath File path to read/write
     * @param string $mode File open mode ('r' for read, 'w' for write)
     * @param int $chunkSize Size of chunks to read/write at a time
     */
    public function __construct(
        string $filePath,
        string $mode = 'r',
        int $chunkSize = 8192
    ) {
        $this->filePath = $filePath;
        $this->fileObject = new SplFileObject($filePath, $mode);
        $this->chunkSize = $chunkSize;
        $this->transformCallable = null;
    }

    /**
     * Set a transformation callback that will be applied to each chunk
     */
    public function setTransformCallable(callable $callable): void
    {
        $this->transformCallable = $callable;
    }

    /**
     * Read file asynchronously, yielding chunks of data
     * @return Fiber Returns a Fiber that yields chunks of data
     */
    public function readAsync(): Fiber
    {
        return new Fiber(function () {
            $this->fileObject->rewind();
            
            while (!$this->fileObject->eof()) {
                $chunk = $this->fileObject->fread($this->chunkSize);
                
                if ($chunk === false) {
                    throw new RuntimeException("Failed to read from file: {$this->filePath}");
                }
                
                if ($this->transformCallable !== null) {
                    $chunk = ($this->transformCallable)($chunk);
                }
                
                if (!empty($chunk)) {
                    Fiber::suspend($chunk);
                }
            }
            
            return null;
        });
    }

    /**
     * Write data asynchronously in chunks
     * @param string $data Data to write
     * @return Fiber Returns a Fiber that yields progress information
     */
    public function writeAsync(string $data): Fiber
    {
        return new Fiber(function () use ($data) {
            $offset = 0;
            $totalLength = strlen($data);
            $totalWritten = 0;
            
            while ($offset < $totalLength) {
                $chunk = substr($data, $offset, $this->chunkSize);
                
                if ($this->transformCallable !== null) {
                    $chunk = ($this->transformCallable)($chunk);
                }
                
                $written = $this->fileObject->fwrite($chunk);
                
                if ($written === false) {
                    throw new RuntimeException("Failed to write to file: {$this->filePath}");
                }
                
                $totalWritten += $written;
                $offset += $this->chunkSize;
                
                if ($totalWritten < $totalLength) {
                    Fiber::suspend([
                        'bytesWritten' => $written,
                        'totalWritten' => $totalWritten,
                        'progress' => ($totalWritten / $totalLength) * 100
                    ]);
                }
            }
            
            // Return final statistics
            Fiber::suspend([
                'bytesWritten' => $totalWritten,
                'totalWritten' => $totalWritten,
                'progress' => 100
            ]);
            
            return null;
        });
    }

    /**
     * Read entire file synchronously
     * @return string Complete file contents
     */
    public function readSync(): string
    {
        $this->fileObject->rewind();
        $content = $this->fileObject->fread($this->fileObject->getSize());
        
        if ($this->transformCallable !== null) {
            $content = ($this->transformCallable)($content);
        }
        
        return $content;
    }

    /**
     * Write data synchronously
     * @param string $data Data to write
     * @return int Number of bytes written
     */
    public function writeSync(string $data): int
    {
        if ($this->transformCallable !== null) {
            $data = ($this->transformCallable)($data);
        }
        
        $written = $this->fileObject->fwrite($data);
        
        if ($written === false) {
            throw new RuntimeException("Failed to write to file: {$this->filePath}");
        }
        
        return $written;
    }
}
