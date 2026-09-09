<?php

declare(strict_types=1);

namespace Tests\Unit;

/**
 * Exception used internally to interrupt controller flow after capturing a response.
 * This replaces the `exit` calls in controllers when testing.
 */
class ResponseCapturedException extends \Exception
{
    public function __construct(
        public readonly mixed $responseData,
        public readonly int $responseStatus,
    ) {
        parent::__construct('Response captured', $responseStatus);
    }
}
