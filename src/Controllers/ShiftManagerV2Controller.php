<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Helpers\Authenticator;
use Engelsystem\Http\Response;
use Engelsystem\Http\Request;
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
        protected Request $request,
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
        $actualPersona = 'public';

        // Check if manager wants to override the view for testing/debugging
        $debugView = $this->request->query->get('debug_view');
        if ($isManager && in_array($debugView, ['manager', 'staff', 'public'], true)) {
            // Manager can switch to any view for testing
            $persona = $debugView;
            $actualPersona = 'manager'; // Keep track of actual permissions

            switch ($debugView) {
                case 'manager':
                    $view = 'ShiftManagerV2/Manager/index.twig';
                    break;
                case 'staff':
                    $view = 'ShiftManagerV2/Staff/index.twig';
                    break;
                case 'public':
                    $view = 'ShiftManagerV2/Public/index.twig';
                    break;
            }
        } else {
            // Normal persona determination
            if ($isManager) {
                $view = 'ShiftManagerV2/Manager/index.twig';
                $persona = 'manager';
                $actualPersona = 'manager';
            } elseif ($isStaff) {
                $view = 'ShiftManagerV2/Staff/index.twig';
                $persona = 'staff';
                $actualPersona = 'staff';
            } elseif ($isPublic) {
                $view = 'ShiftManagerV2/Public/index.twig';
                $persona = 'public';
                $actualPersona = 'public';
            }
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
            'actual_persona' => $actualPersona,
            'is_debug_mode' => $isManager && $debugView,
            'page_title' => 'Shift Manager',
            // Defaults per requirements
            'default_time_start' => '00:00',
            'default_time_end' => '23:59',
            'event_dates' => $eventDates,
        ]);
    }
}
