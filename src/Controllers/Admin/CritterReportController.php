<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Admin;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Helpers\Carbon;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Models\Shifts\ShiftEntry;
use Engelsystem\Models\User\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;

class CritterReportController extends BaseController
{
    /** @var array<string> */
    protected array $permissions = [
        'user.type.admin',
    ];

    public function __construct(protected Response $response)
    {
    }

    public function index(Request $request): Response
    {
        $format = $request->get('format');

        if ($format === 'csv') {
            return $this->exportCsv();
        }

        $users = $this->getUsersData();

        return $this->response->withView(
            'admin/critter-report.twig',
            [
                'title' => __('Critter Report'),
                'users' => $users,
            ]
        );
    }

    private function getUsersData(): array
    {
        $shift_sum_formula = User_get_shifts_sum_query();

        $query = User::with(['personalData', 'state', 'worklogs', 'oauth'])
            ->selectRaw(
                sprintf(
                    '
                        users.*,
                        COUNT(shift_entries.id) AS shift_count,
                            (%s + (
                                SELECT COALESCE(SUM(`hours`) * 3600, 0)
                                FROM `worklogs` WHERE `user_id`=`users`.`id`
                                AND `worked_at` <= NOW()
                            )) AS `shift_length`
                    ',
                    $shift_sum_formula
                )
            )
            ->leftJoin('shift_entries', 'users.id', '=', 'shift_entries.user_id')
            ->leftJoin('shifts', function ($join): void {
                /** @var JoinClause $join */
                $join->on('shift_entries.shift_id', '=', 'shifts.id');
                $join->where(function ($query): void {
                    /** @var Builder $query */
                    $query->where('shifts.end', '<', Carbon::now())
                        ->orWhereNull('shifts.end');
                });
            })
            ->leftJoin('users_state', 'users.id', '=', 'users_state.user_id')
            ->where('users_state.arrived', '=', true)
            ->orWhere(function (EloquentBuilder $userinfo): void {
                $userinfo->where('users_state.arrived', '=', false)
                    ->whereNotNull('users_state.user_info')
                    ->whereNot('users_state.user_info', '');
            })
            ->groupBy('users.id')
            ->orderBy('users.name');

        /** @var User[] $users */
        $users = $query->get();
        $userData = [];

        foreach ($users as $usr) {
            $timeSum = 0;
            /** @var ShiftEntry[] $shiftEntries */
            $shiftEntries = $usr->shiftEntries()
                ->with('shift')
                ->get();
            foreach ($shiftEntries as $entry) {
                if ($entry->freeloaded || $entry->shift->start > Carbon::now()) {
                    continue;
                }
                $timeSum += ($entry->shift->end->timestamp - $entry->shift->start->timestamp);
            }
            foreach ($usr->worklogs as $worklog) {
                $timeSum += $worklog->hours * 3600;
            }

            // Get OAuth user ID
            $oauthUserId = '';
            if ($usr->oauth->isNotEmpty()) {
                $oauthUserId = $usr->oauth->first()->identifier ?? '';
            }

            // Check if user has staff permission
            $isStaff = $usr->hasPermission('user.type.staff');

            $userData[] = [
                'username' => $usr->name,
                'user_id' => $usr->id,
                'oauth_user_id' => $oauthUserId,
                'email' => $usr->email,
                'badge_number' => $usr->personalData->badge_number ?? '',
                'is_staff' => $isStaff,
                'shift_count' => $usr['shift_count'],
                'work_time_hours' => round($timeSum / 3600, 2),
                'goodie_score_minutes' => round($usr['shift_length'] / 60),
                'goodie_score_hours' => round($usr['shift_length'] / 3600, 2),
            ];
        }

        return $userData;
    }

    private function exportCsv(): Response
    {
        $users = $this->getUsersData();

        $csv = 'Username,User ID,OAuth User ID,Email,Badge Number,Is Staff,' .
            "Total Shifts,Length (Hours),Goodie Score (Minutes),Goodie Score (Hours)\n";

        foreach ($users as $user) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $this->escapeCsv($user['username']),
                $user['user_id'],
                $this->escapeCsv($user['oauth_user_id']),
                $this->escapeCsv($user['email']),
                $this->escapeCsv((string) $user['badge_number']),
                $user['is_staff'] ? 'Yes' : 'No',
                $user['shift_count'],
                $user['work_time_hours'],
                $user['goodie_score_minutes'],
                $user['goodie_score_hours']
            );
        }

        return $this->response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="critter-report-' . date('Y-m-d-H-i-s') . '.csv"'
            )
            ->withContent($csv);
    }

    private function escapeCsv(string $value): string
    {
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
