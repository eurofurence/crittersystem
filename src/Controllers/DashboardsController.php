<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Config\Config;
use Engelsystem\Helpers\Version;
use Engelsystem\Http\Response;

class DashboardsController extends BaseController
{
    public function __construct(protected Response $response, protected Config $config, protected Version $version)
    {
    }

    public function index(): Response
    {
        return $this->response->withView(
            'pages/dashboards/management.twig',
            [
                'data' => 'something',
                'version' => $this->version->getVersion(),
            ]
        );
    }
}
