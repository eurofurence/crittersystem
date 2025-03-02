<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Admin;

use Engelsystem\Config\Config;
use Engelsystem\Controllers\BaseController;
use Engelsystem\Controllers\HasUserNotifications;
use Engelsystem\Http\Exceptions\HttpNotFound;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Http\UrlGeneratorInterface;
use Engelsystem\Models\SystemConfig;
use Psr\Log\LoggerInterface;

class SystemConfigController extends BaseController
{
    use HasUserNotifications;

    protected array $permissions = [
        'config.edit',
    ];

    public function __construct(
        protected Response $response,
        protected Config $config,
        protected Redirector $redirect,
        protected UrlGeneratorInterface $url,
        protected LoggerInterface $log,
    ) {
    }

    public function index(Request $request): Response
    {
        /** @var Collection $system_configs */
        $system_configs = SystemConfig::query()
            ->orderBy('id')
            ->get();

        // TODO: This is just some bullshit
        return $this->response->withView(
            'admin/system_config/index.twig',
            ['system_configs' => $system_configs]
        );
    }

    protected function activePage(Request $request): string
    {
        $page = $request->getAttribute('page');

        if (empty($this->options[$page])) {
            throw new HttpNotFound();
        }

        return $page;
    }

}
