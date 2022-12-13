<?php

namespace Engelsystem\Http\Exceptions;

use Throwable;

class HttpForbidden extends HttpException
{
    /**
     * @param array          $headers
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message = '',
        array $headers = [],
        int $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct(403, $message, $headers, $code, $previous);
    }
}
