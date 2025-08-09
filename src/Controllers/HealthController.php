<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Http\Response;

class HealthController extends BaseController
{
    public function __construct(protected Response $response)
    {
    }

    public function index(): Response
    {
        $payload = [
            'status' => 'ok',
            'time' => date(DATE_ATOM),
        ];

        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withContent(json_encode($payload));
    }
}
