<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Config\Config;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Http\Exceptions\HttpForbidden;
use Engelsystem\Http\Response;
use Engelsystem\Models\AngelType;
use Engelsystem\Models\Shifts\Shift;
use Engelsystem\Models\Shifts\ShiftType;
use Engelsystem\Models\Location;
use Engelsystem\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection as SupportCollection;

class DashboardsController extends BaseController
{
    /** @var array<string> */
    protected array $permissions = [
        'admin_shifts', // TODO: Give rights to managers
    ];

    public function __construct(
        private readonly Authenticator $auth,
        protected Response $response,
        protected Config $config,
        protected AngelType $angelType,
        protected Shift $shift,
        protected ShiftType $shiftType,
        protected User $user
    ) {
    }

    // Renders the management dashboard, a condensed time table of all shifts & roles
    public function showManagementDashboard(): Response
    {
        return $this->response->withView(
            'pages/dashboards/management.twig',
            [
                'shiftTypes' => $this->shiftType->all(),
                'shifts' => $this->shift->all(),
            ]
        );
    }

    public function index(): Response
    {
        return $this->showManagementDashboard();
    }

}
