<?php

declare(strict_types=1);

namespace Engelsystem\Http\Exceptions;

class HttpRedirect extends HttpException
{
    /**
     * @param string $url The URL to which the client will be redirected.
     * @param int $statusCode The HTTP status code for the redirect. Defaults to 302.
     * @param array $headers Additional headers to include in the response. Defaults to an empty array.
     * @return void
     */
    public function __construct(
        string $url,
        int $statusCode = 302,
        array $headers = []
    ) {
        $headers = array_merge([
            'Location' => $url,
        ], $headers);

        parent::__construct($statusCode, '', $headers);
    }
}
