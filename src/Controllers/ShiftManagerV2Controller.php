<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Helpers\Authenticator;
use Engelsystem\Http\Response;
use Engelsystem\Config\Config;

class ShiftManagerV2Controller extends BaseController
{
    /**
     * Permission required to access any ShiftManagerV2 view: at least public user_shifts.
     * Specific sub-views are chosen by priority.
     *
     * Priority: Manager > Staff > Public
     */
    protected array $permissions = [
        'user_shifts',
    ];

    public function __construct(
        protected Authenticator $auth,
        protected Response $response,
        protected Config $config,
    ) {
    }

    public function index(): Response
    {
        $user = $this->auth->user();

        // Determine persona by priority
        $isManager = $user?->hasAnyPermission(['user.type.admin', 'admin_shifts']) ?? false;
        $isStaff = $user?->hasPermission('user.type.staff') ?? false;
        $isPublic = $user?->hasPermission('user_shifts') ?? false;

        $view = 'ShiftManagerV2/Public/index.twig';
        $persona = 'public';

        if ($isManager) {
            $view = 'ShiftManagerV2/Manager/index.twig';
            $persona = 'manager';
        } elseif ($isStaff) {
            $view = 'ShiftManagerV2/Staff/index.twig';
            $persona = 'staff';
        } elseif ($isPublic) {
            $view = 'ShiftManagerV2/Public/index.twig';
            $persona = 'public';
        }

        // Get event dates for countdown functionality
        $eventDates = [
            'buildup_start' => $this->config->get('buildup_start'),
            'event_start' => $this->config->get('event_start'),
            'event_end' => $this->config->get('event_end'),
            'teardown_end' => $this->config->get('teardown_end'),
        ];

        return $this->response->withView($view, [
            'persona' => $persona,
            'page_title' => 'Shift Manager',
            // Defaults per requirements
            'default_time_start' => '00:00',
            'default_time_end' => '23:59',
            'event_dates' => $eventDates,
        ]);
    }
}
