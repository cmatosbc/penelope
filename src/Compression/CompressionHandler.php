<?php

namespace Penelope\Compression;

use RuntimeException;

class CompressionHandler implements CompressionInterface
{
    private string $algorithm;
    private int $level;

    /**
     * @param string $algorithm Compression algorithm ('gzip', 'bzip2', or 'deflate')
     * @param int $level Compression level (1-9, where 9 is maximum compression)
     */
    public function __construct(string $algorithm = 'gzip', int $level = 6)
    {
        if (!in_array($algorithm, ['gzip', 'bzip2', 'deflate'])) {
            throw new RuntimeException("Unsupported compression algorithm: {$algorithm}");
        }

        if ($level < 1 || $level > 9) {
            throw new RuntimeException("Compression level must be between 1 and 9");
        }

        $this->algorithm = $algorithm;
        $this->level = $level;

        // Verify that the required extension is loaded
        $this->verifyExtension();
    }

    public function compress(string $data): string
    {
        switch ($this->algorithm) {
            case 'gzip':
                return gzencode($data, $this->level);
            case 'bzip2':
                return bzcompress($data, $this->level);
            case 'deflate':
                return gzdeflate($data, $this->level);
            default:
                throw new RuntimeException("Unsupported compression algorithm");
        }
    }

    public function decompress(string $data): string
    {
        switch ($this->algorithm) {
            case 'gzip':
                $result = gzdecode($data);
                break;
            case 'bzip2':
                $result = bzdecompress($data);
                break;
            case 'deflate':
                $result = gzinflate($data);
                break;
            default:
                throw new RuntimeException("Unsupported compression algorithm");
        }

        if ($result === false) {
            throw new RuntimeException("Failed to decompress data");
        }

        return $result;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function getFileExtension(): string
    {
        return match($this->algorithm) {
            'gzip' => '.gz',
            'bzip2' => '.bz2',
            'deflate' => '.zz',
            default => throw new RuntimeException("Unsupported compression algorithm"),
        };
    }

    private function verifyExtension(): void
    {
        $required = match($this->algorithm) {
            'gzip', 'deflate' => 'zlib',
            'bzip2' => 'bz2',
            default => throw new RuntimeException("Unsupported compression algorithm"),
        };

        if (!extension_loaded($required)) {
            throw new RuntimeException("Required extension not loaded: {$required}");
        }
    }
}
