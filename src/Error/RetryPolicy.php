<?php

namespace Penelope\Error;

class RetryPolicy
{
    private int $maxAttempts;
    private int $delayMs;
    private float $backoffMultiplier;
    private int $maxDelayMs;

    /**
     * @param int $maxAttempts Maximum number of retry attempts
     * @param int $delayMs Initial delay between retries in milliseconds
     * @param float $backoffMultiplier Multiplier for exponential backoff
     * @param int $maxDelayMs Maximum delay between retries in milliseconds
     */
    public function __construct(
        int $maxAttempts = 3,
        int $delayMs = 100,
        float $backoffMultiplier = 2.0,
        int $maxDelayMs = 5000
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->delayMs = $delayMs;
        $this->backoffMultiplier = $backoffMultiplier;
        $this->maxDelayMs = $maxDelayMs;
    }

    /**
     * Execute a callback with retry logic
     * @param callable $callback Function to execute
     * @param callable|null $onRetry Optional callback to execute before each retry
     * @return mixed Result from the callback
     * @throws \Exception If all retry attempts fail
     */
    public function execute(callable $callback, ?callable $onRetry = null): mixed
    {
        $attempt = 1;
        $currentDelay = $this->delayMs;
        $lastException = null;

        while ($attempt <= $this->maxAttempts) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;
                
                if ($attempt === $this->maxAttempts) {
                    break;
                }

                if ($onRetry) {
                    $onRetry($attempt, $currentDelay, $e);
                }

                usleep($currentDelay * 1000); // Convert to microseconds
                
                // Calculate next delay with exponential backoff
                $currentDelay = min(
                    (int)($currentDelay * $this->backoffMultiplier),
                    $this->maxDelayMs
                );
                
                $attempt++;
            }
        }

        throw new \RuntimeException(
            "Operation failed after {$this->maxAttempts} attempts",
            0,
            $lastException
        );
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getInitialDelay(): int
    {
        return $this->delayMs;
    }

    public function getBackoffMultiplier(): float
    {
        return $this->backoffMultiplier;
    }

    public function getMaxDelay(): int
    {
        return $this->maxDelayMs;
    }
}
