<?php

declare(strict_types=1);

namespace Engelsystem\Http\Exceptions;

class HttpTemporaryRedirect extends HttpRedirect
{
    /**
     * @param string $url The URL to be used.
     * @param array $headers Optional. An array of headers to be included.
     * @return void
     */
    public function __construct(
        string $url,
        array $headers = []
    ) {
        parent::__construct($url, 302, $headers);
    }
}
