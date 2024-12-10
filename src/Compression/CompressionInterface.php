<?php

namespace Penelope\Compression;

interface CompressionInterface
{
    /**
     * Compress the given data
     * @param string $data Data to compress
     * @return string Compressed data
     */
    public function compress(string $data): string;

    /**
     * Decompress the given data
     * @param string $data Data to decompress
     * @return string Decompressed data
     */
    public function decompress(string $data): string;

    /**
     * Get the compression algorithm name
     * @return string
     */
    public function getAlgorithm(): string;

    /**
     * Get the file extension associated with this compression
     * @return string
     */
    public function getFileExtension(): string;
}
