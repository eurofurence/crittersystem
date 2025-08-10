<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Config\Config;
use Engelsystem\Http\Response;

class MaintenanceController extends BaseController
{
    public function __construct(
        protected Response $response,
        protected Config $config
    ) {
    }

    /**
     * Show maintenance page or return 204 if not in maintenance mode
     */
    public function index(): Response
    {
        // Check if maintenance mode is enabled
        if (!$this->config->get('maintenance', false)) {
            // Not in maintenance mode, return 204 to continue normal flow
            return $this->response->withStatus(204);
        }

        // In maintenance mode, return 503 with maintenance page
        $maintenance = file_get_contents(__DIR__ . '/../../resources/views/static/maintenance.html');

        // Replace placeholders if needed
        $appName = $this->config->get('app_name', 'Website');
        $maintenance = str_replace('%APP_NAME%', htmlspecialchars($appName), $maintenance);

        return $this->response
            ->withStatus(503)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Retry-After', '3600') // Suggest retry after 1 hour
            ->withContent($maintenance);
    }
}
