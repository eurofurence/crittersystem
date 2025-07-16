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

        $query = $this->shift
            ->join('shift_types', 'shifts.shift_type_id', '=', 'shift_types.id')
            ->join('needed_angel_types', 'shifts.id', '=', 'needed_angel_types.shift_id')
            ->join('angel_types', 'needed_angel_types.angel_type_id', '=', 'angel_types.id')
            ->join('locations', 'shifts.location_id', '=', 'locations.id')
            ->leftJoin('shift_entries', function($join){
                $join
                    ->on('needed_angel_types.shift_id', '=', 'shift_entries.shift_id')
                    ->on('needed_angel_types.angel_type_id', '=', 'shift_entries.angel_type_id');
            })
            ->leftJoin('users', 'shift_entries.user_id', '=', 'users.id')
            ->select('shifts.title AS shifts_title',
                     'shifts.description AS shifts_description',
                     'shifts.url AS shifts_url',
                     'shifts.start AS shifts_start',
                     'shifts.end AS shifts_end',
                     'shifts.shift_type_id AS shifts_type_id',
                     'shift_types.name AS shift_types_name',
                     'shift_types.description AS shift_types_description',
                     'needed_angel_types.count AS needed_angel_types_count',
                     'angel_types.name AS angel_types_name',
                     'angel_types.description AS angel_types_description',
                     'locations.id AS locations_id',
                     'locations.name AS locations_name',
                     'locations.description AS locations_description',
                     'users.id AS users_id',
                     'users.name AS users_name')
            ->get();

        return $this->response->withView(
            'pages/dashboards/management.twig',
            [
                'shiftTypes' => $this->shiftType->all(),
                'shifts' => $query,
            ]
        );
    }

    public function index(): Response
    {
        return $this->showManagementDashboard();
    }
}
