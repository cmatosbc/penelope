<?php

namespace Penelope\Error;

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use RuntimeException;

class ErrorHandler
{
    private LoggerInterface $logger;
    private RetryPolicy $retryPolicy;

    public function __construct(
        ?LoggerInterface $logger = null,
        ?RetryPolicy $retryPolicy = null
    ) {
        $this->logger = $logger ?? $this->createDefaultLogger();
        $this->retryPolicy = $retryPolicy ?? new RetryPolicy();
    }

    /**
     * Execute an operation with retry and logging
     * @param callable $operation Operation to execute
     * @param string $context Description of the operation for logging
     * @return mixed Result of the operation
     * @throws RuntimeException If operation fails after all retries
     */
    public function executeWithRetry(callable $operation, string $context): mixed
    {
        return $this->retryPolicy->execute(
            $operation,
            function (int $attempt, int $delay, \Exception $error) use ($context) {
                $this->logger->warning(
                    "{$context} failed (attempt {$attempt}), retrying in {$delay}ms",
                    [
                        'error' => $error->getMessage(),
                        'attempt' => $attempt,
                        'delay_ms' => $delay
                    ]
                );
            }
        );
    }

    /**
     * Log an error with context
     */
    public function logError(\Throwable $error, string $context, array $extra = []): void
    {
        $this->logger->error(
            "{$context}: {$error->getMessage()}",
            array_merge(
                [
                    'error_class' => get_class($error),
                    'file' => $error->getFile(),
                    'line' => $error->getLine(),
                    'trace' => $error->getTraceAsString()
                ],
                $extra
            )
        );
    }

    /**
     * Create a default logger that writes to a file
     */
    private function createDefaultLogger(): LoggerInterface
    {
        $logger = new Logger('penelope');
        $logger->pushHandler(new StreamHandler(
            dirname(__DIR__, 2) . '/var/log/penelope.log',
            Logger::DEBUG
        ));
        return $logger;
    }
}
