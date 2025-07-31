<?php

declare(strict_types=1);

namespace Engelsystem\Http\Exceptions;

class HttpPermanentRedirect extends HttpRedirect
{
    /**
     * @param string $url The URL to be used for the request.
     * @param array $headers Optional headers to include in the request.
     * @return void
     */
    public function __construct(
        string $url,
        array $headers = []
    ) {
        parent::__construct($url, 301, $headers);
    }
}
