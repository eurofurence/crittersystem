<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Config\Config;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Http\Response;
use Engelsystem\Models\AngelType;
use Engelsystem\Models\Shifts\Shift;
use Engelsystem\Models\Shifts\ShiftType;
use Engelsystem\Models\User\User;

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

    // Renders the shift overview, a condensed time table of all shifts & roles
    public function showShiftOverviewDashboard(): Response
    {
        // Get all data we want to display, one row per shift and critter (type)
        $query = $this->shift
            ->join('shift_types', 'shifts.shift_type_id', '=', 'shift_types.id')
            ->join('needed_angel_types', 'shifts.id', '=', 'needed_angel_types.shift_id')
            ->join('angel_types', 'needed_angel_types.angel_type_id', '=', 'angel_types.id')
            ->join('locations', 'shifts.location_id', '=', 'locations.id')
            ->leftJoin('shift_entries', function ($join): void {
                $join
                    ->on('needed_angel_types.shift_id', '=', 'shift_entries.shift_id')
                    ->on('needed_angel_types.angel_type_id', '=', 'shift_entries.angel_type_id');
            })
            ->leftJoin('users', 'shift_entries.user_id', '=', 'users.id')
            ->select(
                'shifts.id AS shifts_id',
                'shifts.title AS shifts_title',
                'shifts.description AS shifts_description',
                'shifts.url AS shifts_url',
                'shifts.start AS shifts_start',
                'shifts.end AS shifts_end',
                'shifts.shift_type_id AS shifts_type_id',
                'shift_types.name AS shift_types_name',
                'shift_types.description AS shift_types_description',
                'needed_angel_types.count AS needed_angel_types_count',
                'angel_types.id AS angel_types_id',
                'angel_types.name AS angel_types_name',
                'angel_types.description AS angel_types_description',
                'locations.id AS locations_id',
                'locations.name AS locations_name',
                'locations.description AS locations_description',
                'users.id AS users_id',
                'users.name AS users_name'
            )
            ->orderBy('shifts_start', 'asc')
            ->orderBy('shifts_end', 'asc')
            ->orderBy('shifts_title', 'asc')
            ->orderBy('angel_types.id', 'asc')
            ->get();

        // Take chunked data and render it down to one row per shifts_id
        $shifts_arr = [];
        $prev_shift_id = -1;
        // ['shifts_type_id' => ['wanted' => int, 'users' => [['users_id' => 'user_name'], ...]]]
        $current_shift_info = [];
        foreach ($query->all() as $shift_data) {
            if ($prev_shift_id !== $shift_data->shifts_id) {
                // Add new container for shift data from DB and critter_types array
                $shifts_arr[$shift_data->shifts_id] = ['shift' => $shift_data, 'critter_types' => []];
                $current_shift_info = [];
            }
            // Ensure shift type id key is in $current_shift_info
            if (!array_key_exists($shift_data->shifts_type_id, $current_shift_info)) {
                $current_shift_info[$shift_data->angel_types_id] = [
                    'wanted' => $shift_data->needed_angel_types_count,
                    'have' => 0,
                    'users' => [],
                ];
                $shifts_arr[$shift_data->shifts_id]['critter_types'] = $current_shift_info;
            }
            // Add user to apropriate shift type array
            if ($shift_data->users_id !== null) {
                $current_shift_info[$shift_data->angel_types_id]['users'][$shift_data->users_id] = $shift_data->users_name;
                $current_shift_info[$shift_data->angel_types_id]['have']++;
                $shifts_arr[$shift_data->shifts_id]['critter_types'] = $current_shift_info;
            }
            $prev_shift_id = $shift_data->shifts_id;
        }
        $shifts = collect($shifts_arr);

        // Create a list of critter types for the table header, mapped to their DB ids
        $critter_types = $query->mapWithKeys(
            fn($row, int $key): array => [$row->angel_types_id => $row->angel_types_name]
        )->sort()->unique()->flip()->all();

        return $this->response->withView(
            'pages/dashboards/shift_overview.twig',
            [
                'critter_types' => $critter_types,
                'shifts' => $shifts,
            ]
        );
    }

    public function index(): Response
    {
        return $this->showShiftOverviewDashboard();
    }
}
