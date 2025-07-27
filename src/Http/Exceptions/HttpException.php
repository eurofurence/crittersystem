<?php

declare(strict_types=1);

namespace Engelsystem\Http\Exceptions;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    /**
     * @param int $statusCode
     * @param string $message
     * @param array $headers
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        protected int $statusCode,
        string $message = '',
        protected array $headers = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {

        parent::__construct($message, $code, $previous);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
